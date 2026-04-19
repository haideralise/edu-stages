<?php

namespace App\Services;

use App\Models\EduClass;
use App\Models\EduClassUser;
use App\Models\EduOrder;
use App\Models\WpPostmeta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Common\ArrayServiceCommon;
use App\Services\Common\DateDayWeekService;
use App\Services\Common\StudentFeeServiceCommon;
use Throwable;

/**
 * Ported from edu2/services/Common/StudentPaymentServiceCommon.php (10eng §8).
 */
class StudentPaymentServiceCommon
{
    public const ORDER_SOURCE_WHATSAPP = 'manual';

    public const ORDER_SOURCE_WOOCOMMERCE = 'woocommerce';

    /**
     * @var array<int, array>|null
     */
    private ?array $allClassesById = null;

    public function __construct(
        private readonly ArrayServiceCommon $arrayServiceCommon,
        private readonly DateDayWeekService $dateDayWeekService,
        private readonly StudentFeeServiceCommon $studentFeeServiceCommon,
        private readonly ClassStudentQueryService $classStudentQueryService
    ) {}

    /**
     * @return array{woocommerce: array, whatsapp: array}
     */
    public function getStudentAllPayments(int $student_id): array
    {
        return [
            'woocommerce' => $this->getStudentOrders($student_id),
            'whatsapp' => $this->getWhatsappOrders($student_id),
        ];
    }

    /**
     * @return array<int, array{woocommerce: array, whatsapp: array}>
     */
    public function getStudentPaymentsBatch(
        array $student_ids,
        ?string $month = null,
        ?int $class_year = null,
        ?int $class_id = null
    ): array {
        if ($student_ids === []) {
            return [];
        }

        try {
            $classes_for_query = $this->getStudentClassesWithCurrentPeriods($student_ids);

            if ($classes_for_query === []) {
                $result = [];
                foreach ($student_ids as $sid) {
                    $result[(int) $sid] = [
                        'woocommerce' => [],
                        'whatsapp' => [],
                    ];
                }

                return $result;
            }

            $wa_result = $this->getOrdersByClassPeriodsBatch(
                $classes_for_query,
                $student_ids,
                self::ORDER_SOURCE_WHATSAPP
            );
            $wc_result = $this->getOrdersByClassPeriodsBatch(
                $classes_for_query,
                $student_ids,
                self::ORDER_SOURCE_WOOCOMMERCE
            );

            $result = [];
            foreach ($student_ids as $sid) {
                $sid = (int) $sid;
                $result[$sid] = [
                    'woocommerce' => $wc_result['orders'][$sid] ?? [],
                    'whatsapp' => $wa_result['orders'][$sid] ?? [],
                ];
            }

            return $result;
        } catch (Throwable $e) {
            Log::warning('[StudentPaymentServiceCommon.getStudentPaymentsBatch] ' . $e->getMessage());
            $result = [];
            foreach ($student_ids as $sid) {
                $result[(int) $sid] = [
                    'woocommerce' => [],
                    'whatsapp' => [],
                ];
            }

            return $result;
        }
    }

    /**
     * @return list<array{class_id: int, month: string, year: int}>
     */
    private function getStudentClassesWithCurrentPeriods(array $student_ids): array
    {
        if ($student_ids === []) {
            return [];
        }

        try {
            $ids = array_map('intval', $student_ids);

            $classIds = EduOrder::query()
                ->whereIn('user_id', $ids)
                ->whereNotNull('class_id')
                ->distinct()
                ->pluck('class_id')
                ->filter()
                ->map(fn($id) => (int) $id)
                ->values()
                ->all();

            if ($classIds === []) {
                $q = EduClassUser::query()->select('class_id')->distinct();
                $q->where(function ($outer) use ($ids) {
                    foreach ($ids as $uid) {
                        $outer->orWhere(function ($w) use ($uid) {
                            $w->whereJsonContains('student', $uid)
                                ->orWhereJsonContains('student_transfer', $uid);
                        });
                    }
                });
                $classIds = $q->pluck('class_id')->map(fn($id) => (int) $id)->values()->all();
            }

            if ($classIds === []) {
                return [];
            }

            $class_ids_unique = array_values(array_unique($classIds, SORT_REGULAR));

            $latestRows = EduClassUser::query()
                ->whereIn('class_id', $class_ids_unique)
                ->whereNotNull('month')
                ->where('month', '!=', '')
                ->orderBy('class_id')
                ->orderByDesc('class_year')
                ->orderByDesc('month')
                ->get(['class_id', 'month', 'class_year']);

            $latestByClassId = [];
            foreach ($latestRows as $row) {
                $cid = (int) $row->class_id;
                if (! isset($latestByClassId[$cid])) {
                    $latestByClassId[$cid] = $row;
                }
            }

            $classes_for_query = [];
            $processed_classes = [];

            foreach ($classIds as $class_id) {
                if (isset($processed_classes[$class_id])) {
                    continue;
                }

                $class_user = $latestByClassId[$class_id] ?? null;

                if ($class_user && $class_user->month !== null && $class_user->month !== '') {
                    $classes_for_query[] = [
                        'class_id' => $class_id,
                        'month' => (string) $class_user->month,
                        'year' => (int) ($class_user->class_year ?? date('Y')),
                    ];
                    $processed_classes[$class_id] = true;
                }
            }

            return $classes_for_query;
        } catch (Throwable $e) {
            Log::warning('[StudentPaymentServiceCommon.getStudentClassesWithCurrentPeriods] Error: ' . $e->getMessage());

            return [];
        }
    }

    public function getStudentOrders(int $student_id): array
    {
        return $this->getStudentOrdersOptimized($student_id);
    }

    /**
     * @return array<int, array>
     */
    public function getStudentOrdersOptimized(int $student_id): array
    {
        try {
            $order_meta = WpPostmeta::query()
                ->where('meta_key', '_customer_user')
                ->where('meta_value', (string) $student_id)
                ->get(['post_id', 'meta_key', 'meta_value']);

            if ($order_meta->isEmpty()) {
                Log::debug("[StudentPaymentServiceCommon.getStudentOrdersOptimized] No orders for student_id: {$student_id}");

                return [];
            }

            $order_ids = $order_meta->pluck('post_id')->map(fn($id) => (int) $id)->unique()->values()->all();

            $order_list = [];
            if ($order_ids !== []) {
                $list = DB::table('woocommerce_order_items as oi')
                    ->join('postmeta as pm', 'pm.post_id', '=', 'oi.order_id')
                    ->join('posts as p', 'p.ID', '=', 'oi.order_id')
                    ->whereIn('oi.order_id', $order_ids)
                    ->whereIn('p.post_status', ['wc-completed', 'wc-refunded'])
                    ->select([
                        'oi.order_id',
                        'oi.order_item_id',
                        'oi.order_item_name',
                        'p.post_date',
                        'p.post_status',
                        'pm.meta_key',
                        'pm.meta_value',
                    ])
                    ->get();

                $order_list = $this->mergeWooOrderMetaRows($list);
            }

            return $this->filterAndEnrichWooOrderList($order_list);
        } catch (Throwable $e) {
            Log::warning("[StudentPaymentServiceCommon.getStudentOrdersOptimized] Error retrieving orders for student_id {$student_id}: " . $e->getMessage());

            return [];
        }
    }

    /**
     * @param  iterable<object|array<string, mixed>>  $list
     * @return array<int, array<string, mixed>>
     */
    private function mergeWooOrderMetaRows(iterable $list): array
    {
        $order_list = [];
        foreach ($list as $value) {
            $row = (array) $value;
            $oid = (int) $row['order_id'];
            if (empty($order_list[$oid])) {
                $order_list[$oid] = $row;
            }
            $order_list[$oid][$row['meta_key']] = $row['meta_value'];
        }

        return $order_list;
    }

    /**
     * @param  array<int, array<string, mixed>>  $order_list
     * @return array<int, array<string, mixed>>
     */
    private function filterAndEnrichWooOrderList(array $order_list): array
    {
        $all_classes = $this->getAllClassesKeyedById();

        foreach ($order_list as $key => $order) {
            $item_name = (string) ($order['order_item_name'] ?? '');
            if (strpos($item_name, ' - ') === false || strpos($item_name, ',') === false) {
                Log::error("[StudentPaymentServiceCommon.getStudentOrdersOptimized] Invalid order format for order_id: {$key}");
                unset($order_list[$key]);
                continue;
            }
            if (strpos($item_name, '游泳班') === false && strpos($item_name, '泳隊訓練') === false) {
                unset($order_list[$key]);
                continue;
            }

            [$product_name, $datetime] = explode(' - ', $item_name, 2);
            [$pa_month, $pa_time] = explode(',', $datetime, 2);
            $class_name = trim($product_name) . mb_substr(trim($pa_time), 1);

            $class_found = false;
            foreach ($all_classes as $cls_id => $cls) {
                if (($cls['class_name'] ?? '') === $class_name) {
                    $class_found = true;
                    $order_list[$key]['class_name'] = $class_name;
                    $order_list[$key]['class_id'] = $cls_id;
                    $order_list[$key]['month'] = trim($pa_month);
                    $order_list[$key]['time'] = trim($pa_time);
                    break;
                }
            }

            if (! $class_found) {
                Log::error("[StudentPaymentServiceCommon.getStudentOrdersOptimized] Class not found for order_id: {$key}, class_name: {$class_name}");
                unset($order_list[$key]);
                continue;
            }

            $_date_time = strtotime((string) $order['post_date']);
            $_y = (int) date('Y', $_date_time);
            $order_month = (int) date('n', $_date_time);

            $class_month = $this->dateDayWeekService->get_array_month($item_name);
            $class_month = (int) reset($class_month);

            if ($order_month > 9 && $class_month < 5) {
                $_y++;
            }

            $order_list[$key]['class_year'] = $_y;
        }

        return $order_list;
    }

    /**
     * @param  list<int>  $student_ids
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function getStudentOrdersOptimizedBatch(array $student_ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $student_ids))));
        $empty = [];
        foreach ($ids as $sid) {
            $empty[$sid] = [];
        }
        if ($ids === []) {
            return [];
        }

        try {
            $metaValues = array_map(static fn (int $id): string => (string) $id, $ids);
            $order_meta = WpPostmeta::query()
                ->where('meta_key', '_customer_user')
                ->whereIn('meta_value', $metaValues)
                ->get(['post_id', 'meta_key', 'meta_value']);

            if ($order_meta->isEmpty()) {
                return $empty;
            }

            $orderOwners = [];
            foreach ($order_meta as $row) {
                $orderOwners[(int) $row->post_id] = (int) $row->meta_value;
            }

            $order_ids = array_keys($orderOwners);
            $order_list_by_oid = [];
            if ($order_ids !== []) {
                $list = DB::table('woocommerce_order_items as oi')
                    ->join('postmeta as pm', 'pm.post_id', '=', 'oi.order_id')
                    ->join('posts as p', 'p.ID', '=', 'oi.order_id')
                    ->whereIn('oi.order_id', $order_ids)
                    ->whereIn('p.post_status', ['wc-completed', 'wc-refunded'])
                    ->select([
                        'oi.order_id',
                        'oi.order_item_id',
                        'oi.order_item_name',
                        'p.post_date',
                        'p.post_status',
                        'pm.meta_key',
                        'pm.meta_value',
                    ])
                    ->get();

                $order_list_by_oid = $this->mergeWooOrderMetaRows($list);
            }

            $studentOrders = $empty;
            foreach ($order_list_by_oid as $oid => $orderRow) {
                $uid = $orderOwners[$oid] ?? null;
                if ($uid !== null && array_key_exists($uid, $studentOrders)) {
                    $studentOrders[$uid][$oid] = $orderRow;
                }
            }

            foreach ($ids as $sid) {
                $studentOrders[$sid] = $this->filterAndEnrichWooOrderList($studentOrders[$sid]);
            }

            return $studentOrders;
        } catch (Throwable $e) {
            Log::warning('[StudentPaymentServiceCommon.getStudentOrdersOptimizedBatch] ' . $e->getMessage());

            return $empty;
        }
    }

    /**
     * @param  list<int>  $student_ids
     * @return array<int, list<array<string, mixed>>>
     */
    private function getStudentWhatsappOrdersBatch(array $student_ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $student_ids))));
        $out = [];
        foreach ($ids as $sid) {
            $out[$sid] = [];
        }
        if ($ids === []) {
            return [];
        }

        try {
            $orders = EduOrder::query()
                ->whereIn('user_id', $ids)
                ->where('order_source', self::ORDER_SOURCE_WHATSAPP)
                ->get();

            foreach ($orders as $m) {
                $uid = (int) $m->user_id;
                if (isset($out[$uid])) {
                    $out[$uid][] = $m->getAttributes();
                }
            }
        } catch (Throwable $e) {
            Log::warning('[StudentPaymentServiceCommon.getStudentWhatsappOrdersBatch] ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * @param  list<int>  $student_ids
     * @return array<int, array{woocommerce: array, whatsapp: array}>
     */
    private function getStudentAllPaymentsBatch(array $student_ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $student_ids))));
        $woo = $this->getStudentOrdersOptimizedBatch($ids);
        $wa = $this->getStudentWhatsappOrdersBatch($ids);
        $out = [];
        foreach ($ids as $sid) {
            $out[$sid] = [
                'woocommerce' => $woo[$sid] ?? [],
                'whatsapp' => $wa[$sid] ?? [],
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array>
     */
    public function getWhatsappOrders(int $student_id): array
    {
        try {
            $orders = EduOrder::query()
                ->where('user_id', $student_id)
                ->where('order_source', self::ORDER_SOURCE_WHATSAPP)
                ->get();

            if ($orders->isEmpty()) {
                Log::debug("[StudentPaymentServiceCommon.getWhatsappOrders] No orders for student_id: {$student_id}");

                return [];
            }

            return $orders->map(fn($m) => $m->getAttributes())->all();
        } catch (Throwable $e) {
            Log::warning("[StudentPaymentServiceCommon.getWhatsappOrders] student_id {$student_id}: " . $e->getMessage());

            return [];
        }
    }

    /**
     * @param  array<int, array>  $order_list
     * @param  array<int, array>  $order_all
     * @param  array<string, mixed>  $no_more_renew
     * @return list<array<string, mixed>>
     */
    public function displayOrders(array $order_list, array $order_all, int $student_id, array $no_more_renew): array
    {
        return $this->displayOrdersBatch([
            [
                'order_list' => $order_list,
                'order_all' => $order_all,
                'student_id' => $student_id,
                'no_more_renew' => $no_more_renew,
            ],
        ])[0] ?? [];
    }

    /**
     * @param  list<array{order_list: array<int, array>, order_all: array<int, array>, student_id: int, no_more_renew?: array<string, mixed>}>  $contexts
     * @return list<list<array<string, mixed>>>
     */
    private function displayOrdersBatch(array $contexts): array
    {
        $out = [];
        foreach ($contexts as $idx => $ctx) {
            $out[$idx] = $this->displayOrdersForSingleContext(
                $ctx['order_list'] ?? [],
                $ctx['order_all'] ?? [],
                (int) ($ctx['student_id'] ?? 0),
                $ctx['no_more_renew'] ?? []
            );
        }

        return $out;
    }

    /**
     * @param  array<int, array>  $order_list
     * @param  array<int, array>  $order_all
     * @param  array<string, mixed>  $no_more_renew
     * @return list<array<string, mixed>>
     */
    private function displayOrdersForSingleContext(array $order_list, array $order_all, int $student_id, array $no_more_renew): array
    {
        $display_orders = [];

        $allClassRows = [];
        foreach ($order_list as $order) {
            if (($order['post_status'] ?? '') !== 'wc-completed') {
                continue;
            }
            $item_name = (string) ($order['order_item_name'] ?? '');
            if (strpos($item_name, ' - ') === false || strpos($item_name, ',') === false) {
                continue;
            }
            [$product_name, $datetime] = explode(' - ', $item_name, 2);
            [$pa_month, $pa_time] = explode(',', $datetime, 2);
            $product_name = trim($product_name);
            $pa_month = trim($pa_month);
            $pa_time = trim($pa_time);
            $class_name = $product_name . mb_substr($pa_time, 1);
            $allClassRows[$class_name] = null;
        }
        if ($allClassRows !== []) {
            $foundClasses = EduClass::query()
                ->whereIn('class_name', array_keys($allClassRows))
                ->get()
                ->mapWithKeys(fn($m) => [$m->class_name => $m->getAttributes()])
                ->all();
            $allClassRows = $foundClasses;
        }

        $renewSpecs = [];
        foreach ($order_list as $order) {
            if (($order['post_status'] ?? '') !== 'wc-completed') {
                continue;
            }
            $item_name = (string) ($order['order_item_name'] ?? '');
            if (strpos($item_name, ' - ') === false || strpos($item_name, ',') === false) {
                continue;
            }
            [$product_name, $datetime] = explode(' - ', $item_name, 2);
            [$pa_month, $pa_time] = explode(',', $datetime, 2);
            $product_name = trim($product_name);
            $pa_month = trim($pa_month);
            $pa_time = trim($pa_time);
            $class_name = $product_name . mb_substr($pa_time, 1);
            if (($allClassRows[$class_name] ?? null) === null) {
                continue;
            }

            $order_date = $order['_paid_date'] ?? $order['post_date'] ?? date('Y-m-d H:i:s');
            $order_timestamp = strtotime((string) $order_date);
            $order_y = (int) date('Y', $order_timestamp);
            $order_n = (int) date('n', $order_timestamp);
            $month_last = $this->dateDayWeekService->get_array_month($pa_month);
            $month_last = (int) end($month_last);
            $class_year = ($order_n > $month_last) ? ($order_y + 1) : $order_y;
            $_amount = $order['_order_total'] ?? 0;
            $oid = (string) ($order['order_id'] ?? '');

            $renewSpecs[] = [
                'key' => $oid,
                'class_name' => $class_name,
                'pa_month' => $pa_month,
                'class_year' => $class_year,
                'amount' => $_amount,
            ];
        }

        $renewByOrderId = $renewSpecs !== []
            ? $this->studentFeeServiceCommon->getRenewPriceBatch($student_id, $renewSpecs)
            : [];

        foreach ($order_list as $order) {
            if (($order['post_status'] ?? '') !== 'wc-completed') {
                continue;
            }

            $item_name = (string) ($order['order_item_name'] ?? '');
            if (strpos($item_name, ' - ') === false || strpos($item_name, ',') === false) {
                continue;
            }
            [$product_name, $datetime] = explode(' - ', $item_name, 2);
            [$pa_month, $pa_time] = explode(',', $datetime, 2);
            $product_name = trim($product_name);
            $pa_month = trim($pa_month);
            $pa_time = trim($pa_time);
            $class_name = $product_name . mb_substr($pa_time, 1);

            $class = $allClassRows[$class_name] ?? null;
            if ($class === null) {
                continue;
            }

            $next = $this->dateDayWeekService->get_next_year_month((int) $order['class_year'], $order['month']);
            $found_order = $this->findNextMonthOrder($order_all, (int) $order['class_id'], $next['month'], (int) $next['class_year']);
            $renew_status = $found_order ? 1 : 0;
            $renew_stop_status = empty($no_more_renew['woo_' . $order['order_id']]) ? 0 : 1;

            $renew_button_text = $renew_status ? '已續費' : '待續費';
            if ($renew_stop_status) {
                $renew_button_text = '不續費';
            }

            $_system_year = (int) date('Y');
            $_system_month = (int) date('n');
            if ((int) date('j') < 16) {
                $_system_month--;
                if ($_system_month === 0) {
                    $_system_month = 12;
                    $_system_year--;
                }
            }
            $renew_current = (
                $this->dateDayWeekService->is_current_year($order['class_year'], $_system_year)
                && $this->dateDayWeekService->is_current_month($order['month'], $_system_month)
            ) ? 1 : 0;

            $calculated_year = $this->calculateClassYearForOrder($order, $pa_month);
            $oid = (string) ($order['order_id'] ?? '');
            $renew = $renewByOrderId[$oid] ?? [];

            $display_orders[] = [
                'order' => $order,
                'class' => $class,
                'class_name' => $class_name,
                'pa_month' => $pa_month,
                'renew' => $renew,
                'renew_status' => $renew_status,
                'renew_stop_status' => $renew_stop_status,
                'renew_button_text' => $renew_button_text,
                'renew_current' => $renew_current,
                'class_year' => $calculated_year,
            ];
        }

        return $display_orders;
    }

    /**
     * @param  array<int, array>  $whatsapp_orders
     * @param  array<int, array>  $order_all
     * @param  array<int, array>  $classes  keyed by class_id
     * @param  array<string, mixed>  $no_more_renew
     * @return list<array<string, mixed>>
     */
    public function prepareWhatappOrders(
        array $whatsapp_orders,
        array $order_all,
        int $student_id,
        array $classes,
        array $no_more_renew
    ): array {
        return $this->prepareWhatappOrdersBatch([
            [
                'whatsapp_orders' => $whatsapp_orders,
                'order_all' => $order_all,
                'student_id' => $student_id,
                'classes' => $classes,
                'no_more_renew' => $no_more_renew,
            ],
        ])[0] ?? [];
    }

    /**
     * @param  list<array{whatsapp_orders: array<int, array>, order_all: array<int, array>, student_id: int, classes: array<int, array>, no_more_renew?: array<string, mixed>}>  $contexts
     * @return list<list<array<string, mixed>>>
     */
    private function prepareWhatappOrdersBatch(array $contexts): array
    {
        $out = [];
        foreach ($contexts as $idx => $ctx) {
            $out[$idx] = $this->prepareWhatappOrdersForSingleContext(
                $ctx['whatsapp_orders'] ?? [],
                $ctx['order_all'] ?? [],
                (int) ($ctx['student_id'] ?? 0),
                $ctx['classes'] ?? [],
                $ctx['no_more_renew'] ?? []
            );
        }

        return $out;
    }

    /**
     * @param  array<int, array>  $whatsapp_orders
     * @param  array<int, array>  $order_all
     * @param  array<int, array>  $classes
     * @param  array<string, mixed>  $no_more_renew
     * @return list<array<string, mixed>>
     */
    private function prepareWhatappOrdersForSingleContext(
        array $whatsapp_orders,
        array $order_all,
        int $student_id,
        array $classes,
        array $no_more_renew
    ): array {
        $prepared_orders = [];

        if (! $whatsapp_orders) {
            return $prepared_orders;
        }

        $_system_year = (int) date('Y');
        $_system_month = (int) date('n');
        if ((int) date('j') < 16) {
            $_system_month--;
            if ($_system_month === 0) {
                $_system_month = 12;
                $_system_year--;
            }
        }

        $rows = [];
        foreach ($whatsapp_orders as $rowIndex => $order) {
            $o = $order;
            $o['class_year'] = $o['class_year'] ? $o['class_year'] : date('Y');

            $_class_name = '';
            if (! empty($classes[$o['class_id']])) {
                $_class_name = $classes[$o['class_id']]['class_name'] ?? '';
            }
            $_month = $o['month'] ?? '';
            $_amount = $o['amount'] ?? 0;
            $class_year = empty($o['class_year']) ? date('Y') : $o['class_year'];

            $rows[] = [
                'order' => $o,
                'row_index' => $rowIndex,
                'class_name' => $_class_name,
                'month' => $_month,
                'amount' => $_amount,
                'class_year_for_renew' => $class_year,
            ];
        }

        $renewSpecs = [];
        foreach ($rows as $r) {
            if (! $r['class_name']) {
                continue;
            }
            $renewKey = $this->whatsappRenewBatchKey($r['row_index'], $r['order']);
            $renewSpecs[] = [
                'key' => $renewKey,
                'class_name' => $r['class_name'],
                'pa_month' => $r['month'],
                'class_year' => $r['class_year_for_renew'],
                'amount' => $r['amount'],
            ];
        }

        $renewByKey = $renewSpecs !== []
            ? $this->studentFeeServiceCommon->getRenewPriceBatch($student_id, $renewSpecs)
            : [];

        $emptyRenew = ['price' => 0, 'renew' => '', 'text' => '', 'class_days' => []];

        foreach ($rows as $r) {
            $order = $r['order'];
            $_class_name = $r['class_name'];
            $_month = $r['month'];
            $_amount = $r['amount'];

            $next = $this->dateDayWeekService->get_next_year_month((int) $order['class_year'], $order['month']);
            $found_order = $this->findNextMonthOrder($order_all, (int) $order['class_id'], $next['month'], (int) $next['class_year']);
            $renew_status = $found_order ? 1 : 0;
            $renew_button_text = $renew_status ? '已續費' : '待續費';

            $renew_stop_status = empty($no_more_renew['whatsapp_' . $order['id']]) ? 0 : 1;
            if ($renew_stop_status) {
                $renew_button_text = '不續費';
            }

            $renew_current = (
                $this->dateDayWeekService->is_current_year($order['class_year'], $_system_year)
                && $this->dateDayWeekService->is_current_month($order['month'], $_system_month)
            ) ? 1 : 0;

            if ($_class_name) {
                $rk = $this->whatsappRenewBatchKey($r['row_index'], $order);
                $renew = $renewByKey[$rk] ?? $emptyRenew;
            } else {
                $renew = $emptyRenew;
            }

            $calculated_year = $this->calculateClassYearForWhatsapp($order['order_date'], $_month);

            $prepared_orders[] = [
                'order' => $order,
                'class_name' => $_class_name,
                'month' => $_month,
                'amount' => $_amount,
                'renew' => $renew,
                'renew_status' => $renew_status,
                'renew_button_text' => $renew_button_text,
                'renew_stop_status' => $renew_stop_status,
                'renew_current' => $renew_current,
                'class_year' => $calculated_year,
            ];
        }

        return $prepared_orders;
    }

    /**
     * Stable key for {@see getRenewPriceBatch} (falls back when id is missing).
     */
    private function whatsappRenewBatchKey(int $rowIndex, array $order): string
    {
        $id = (string) ($order['id'] ?? '');

        return $id !== '' ? $id : '__whatsapp_row_'.$rowIndex;
    }

    public function getStudentPaymentByMonth(int $student_id, string $month, int $class_year): ?array
    {
        $all_payments = $this->getStudentAllPayments($student_id);

        foreach ($all_payments['woocommerce'] as $order) {
            if (($order['month'] ?? '') === $month && (int) ($order['class_year'] ?? 0) === $class_year) {
                return $order;
            }
        }

        foreach ($all_payments['whatsapp'] as $order) {
            if (($order['month'] ?? '') === $month && (int) ($order['class_year'] ?? 0) === $class_year) {
                return $order;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array>  $order_list
     * @return list<string>
     */
    public function validatePaymentDataConsistency(array $order_list): array
    {
        $errors = [];

        // 1. Collect all class names in need of validation
        $class_names_to_check = [];
        $orders_to_check = [];

        foreach ($order_list as $order_id => $order) {
            $item_name = (string) ($order['order_item_name'] ?? '');
            if (strpos($item_name, ' - ') === false || strpos($item_name, ',') === false) {
                $errors[] = "Order {$order_id}: Invalid order item name format";
                continue;
            }

            $order_total = (float) ($order['_order_total'] ?? 0);
            if ($order_total < 0) {
                $errors[] = "Order {$order_id}: Invalid order total {$order_total}";
            }

            $paid_date = $order['_paid_date'] ?? null;
            if ($paid_date) {
                $paid_timestamp = strtotime((string) $paid_date);
                if ($paid_timestamp > time()) {
                    $errors[] = "Order {$order_id}: Paid date is in the future";
                }
            }

            [$product_name, $datetime] = explode(' - ', $item_name, 2);
            [$pa_month, $pa_time] = explode(',', $datetime, 2);
            $class_name = trim($product_name) . mb_substr(trim($pa_time), 1);

            $orders_to_check[] = [
                'order_id' => $order_id,
                'class_name' => $class_name,
            ];
            $class_names_to_check[$class_name] = true;
        }

        // 2. Batch query all class names
        if (!empty($class_names_to_check)) {
            $class_names = array_keys($class_names_to_check);
            $class_rows = EduClass::query()
                ->whereIn('class_name', $class_names)
                ->get(['class_name'])
                ->pluck('class_name')
                ->all();
            $found_class_names = array_flip($class_rows);
        } else {
            $found_class_names = [];
        }

        // 3. Validate existence
        foreach ($orders_to_check as $item) {
            if (!isset($found_class_names[$item['class_name']])) {
                $errors[] = "Order {$item['order_id']}: Class name '{$item['class_name']}' not found in database";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    public function calculateClassYearForOrder(array $order, string $pa_month): int
    {
        $order_date = $order['_paid_date'] ?? $order['post_date'] ?? date('Y-m-d H:i:s');
        $order_timestamp = strtotime((string) $order_date);
        $order_year = (int) date('Y', $order_timestamp);
        $order_month_num = (int) date('n', $order_timestamp);

        $class_month_numbers = $this->dateDayWeekService->get_array_month($pa_month);
        $last_class_month = (int) end($class_month_numbers);

        if ($order_month_num >= 10 && $last_class_month <= 4) {
            return $order_year + 1;
        }
        if ($order_month_num > $last_class_month) {
            return $order_year + 1;
        }

        return $order_year;
    }

    /**
     * @return array{orders: array<int, list<array>>, stats: array<string, mixed>}
     */
    public function getOrdersByClassPeriodsBatch(
        array $classes,
        array $student_ids,
        string $order_source = self::ORDER_SOURCE_WHATSAPP
    ): array {
        if ($classes === [] || $student_ids === []) {
            return ['orders' => [], 'stats' => ['total' => 0, 'periods' => 0]];
        }

        try {
            $period_list = [];
            $period_map = [];

            $class_list = array_values($classes);
            $batch_items = [];
            foreach ($class_list as $c) {
                $y = null;
                if (array_key_exists('year', $c) && $c['year'] !== null && $c['year'] !== '') {
                    $y = (int) $c['year'];
                } elseif (array_key_exists('class_year', $c) && $c['class_year'] !== null && $c['class_year'] !== '') {
                    $y = (int) $c['class_year'];
                }
                $batch_items[] = [
                    'class_id' => (int) ($c['class_id'] ?? 0),
                    'month' => $c['month'] ?? '',
                    'year' => $y,
                ];
            }

            $neighbors = $this->classStudentQueryService->getClassPreviousNextPeriodsBatch($batch_items);

            foreach ($class_list as $i => $class_info) {
                $class_month = $class_info['month'];
                $class_year = (int) ($class_info['year'] ?? $class_info['class_year'] ?? 0);

                $prev_period = $neighbors[$i]['prev'] ?? null;
                if ($prev_period) {
                    $key = $prev_period['class_year'] . '-' . $prev_period['month'];
                    if (! isset($period_map[$key])) {
                        $period_list[] = [
                            'month' => $prev_period['month'],
                            'year' => (int) $prev_period['class_year'],
                        ];
                        $period_map[$key] = true;
                    }
                }

                $key = $class_year . '-' . $class_month;
                if (! isset($period_map[$key])) {
                    $period_list[] = [
                        'month' => $class_month,
                        'year' => $class_year,
                    ];
                    $period_map[$key] = true;
                }

                $next_period = $neighbors[$i]['next'] ?? null;
                if ($next_period) {
                    $key = $next_period['class_year'] . '-' . $next_period['month'];
                    if (! isset($period_map[$key])) {
                        $period_list[] = [
                            'month' => $next_period['month'],
                            'year' => (int) $next_period['class_year'],
                        ];
                        $period_map[$key] = true;
                    }
                }
            }

            return $this->queryOrdersByPeriods($student_ids, $period_list, $order_source);
        } catch (Throwable $e) {
            Log::warning('[StudentPaymentServiceCommon.getOrdersByClassPeriodsBatch] ' . $e->getMessage());

            return ['orders' => [], 'stats' => ['total' => 0, 'periods' => 0, 'error' => $e->getMessage()]];
        }
    }

    public function getStudentPreviousOrderMonth(
        int $student_id,
        int $class_id,
        string $current_month,
        int $current_year,
        array $classes = []
    ): ?string {
        $cal = $this->resolvePreviousCalendarForOrder($current_month, $current_year);
        if ($cal === null) {
            return null;
        }

        $payments = $this->getStudentAllPaymentsBatch([(int) $student_id]);
        $p = $payments[(int) $student_id] ?? ['woocommerce' => [], 'whatsapp' => []];
        $classesKeyed = $this->normalizeClassesKeyedByClassId($classes);

        $woo_orders = $this->displayOrders(
            $p['woocommerce'],
            array_merge($p['woocommerce'], $p['whatsapp']),
            $student_id,
            []
        );

        $wa_orders = $this->prepareWhatappOrders(
            $p['whatsapp'],
            array_merge($p['woocommerce'], $p['whatsapp']),
            $student_id,
            $classesKeyed,
            []
        );

        $all_orders = array_merge($woo_orders, $wa_orders);
        $coach_class_ids = $this->buildCoachClassIdsForPreviousOrder($class_id, $classes);

        return $this->findPreviousOrderPaMonthFromDisplayOrders(
            $all_orders,
            $coach_class_ids,
            $cal['month'],
            $cal['year']
        );
    }

    /**
     * @return array<int, string|null>
     */
    public function getStudentPreviousOrderMonthsBatch(
        array $student_ids,
        int $class_id,
        string $current_month,
        int $current_year,
        array $classes = []
    ): array {
        $cal = $this->resolvePreviousCalendarForOrder($current_month, $current_year);
        if ($cal === null) {
            $result = [];
            foreach ($student_ids as $sid) {
                $result[(int) $sid] = null;
            }

            return $result;
        }

        $coach_class_ids = $this->buildCoachClassIdsForPreviousOrder($class_id, $classes);
        $classesKeyed = $this->normalizeClassesKeyedByClassId($classes);

        $uniqueIds = array_values(array_unique(array_filter(array_map('intval', $student_ids))));
        $paymentsBy = $uniqueIds === [] ? [] : $this->getStudentAllPaymentsBatch($uniqueIds);

        $displayContexts = [];
        $waContexts = [];
        foreach ($uniqueIds as $sid) {
            $p = $paymentsBy[$sid] ?? ['woocommerce' => [], 'whatsapp' => []];
            $order_all = array_merge($p['woocommerce'], $p['whatsapp']);
            $displayContexts[] = [
                'order_list' => $p['woocommerce'],
                'order_all' => $order_all,
                'student_id' => $sid,
                'no_more_renew' => [],
            ];
            $waContexts[] = [
                'whatsapp_orders' => $p['whatsapp'],
                'order_all' => $order_all,
                'student_id' => $sid,
                'classes' => $classesKeyed,
                'no_more_renew' => [],
            ];
        }

        $wooDisplayedList = $this->displayOrdersBatch($displayContexts);
        $waPreparedList = $this->prepareWhatappOrdersBatch($waContexts);

        $computed = [];
        foreach ($uniqueIds as $i => $sid) {
            $woo_orders = $wooDisplayedList[$i] ?? [];
            $wa_orders = $waPreparedList[$i] ?? [];
            $all_orders = array_merge($woo_orders, $wa_orders);
            $computed[$sid] = $this->findPreviousOrderPaMonthFromDisplayOrders(
                $all_orders,
                $coach_class_ids,
                $cal['month'],
                $cal['year']
            );
        }

        $result = [];
        foreach ($student_ids as $sid) {
            $result[(int) $sid] = $computed[(int) $sid] ?? null;
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeClassesKeyedByClassId(array $classes): array
    {
        $out = [];
        foreach ($classes as $c) {
            if (is_array($c) && isset($c['class_id'])) {
                $out[(int) $c['class_id']] = $c;
            }
        }

        return $out;
    }

    /**
     * @return array{month: int, year: int}|null
     */
    private function resolvePreviousCalendarForOrder(string $current_month, int $current_year): ?array
    {
        $current_start_month = $this->parseStartMonthNum($current_month);
        if ($current_start_month === null) {
            return null;
        }

        $prev_month_num = ($current_start_month === 1) ? 12 : ($current_start_month - 1);
        $prev_year = ($current_start_month === 1) ? ($current_year - 1) : $current_year;

        return ['month' => $prev_month_num, 'year' => $prev_year];
    }

    /**
     * @return list<int>
     */
    private function buildCoachClassIdsForPreviousOrder(int $class_id, array $classes): array
    {
        $coach_id = 0;
        if ($classes !== []) {
            foreach ($classes as $cls) {
                if (isset($cls['class_id']) && (int) $cls['class_id'] === $class_id) {
                    $coach_id = isset($cls['coach_id']) ? (int) $cls['coach_id'] : 0;
                    break;
                }
            }
        }

        $coach_class_ids = [$class_id];
        if ($coach_id > 0 && $classes !== []) {
            foreach ($classes as $cls) {
                if (isset($cls['coach_id']) && (int) $cls['coach_id'] === $coach_id) {
                    $cid = isset($cls['class_id']) ? (int) $cls['class_id'] : 0;
                    if ($cid > 0 && ! in_array($cid, $coach_class_ids, true)) {
                        $coach_class_ids[] = $cid;
                    }
                }
            }
        }

        return $coach_class_ids;
    }

    /**
     * @param  list<array<string, mixed>>  $all_orders
     */
    private function findPreviousOrderPaMonthFromDisplayOrders(
        array $all_orders,
        array $coach_class_ids,
        int $prev_month_num,
        int $prev_year
    ): ?string {
        foreach ($all_orders as $order) {
            $order_class_id = isset($order['class']['class_id'])
                ? (int) $order['class']['class_id']
                : (isset($order['order']['class_id']) ? (int) $order['order']['class_id'] : 0);

            if (! in_array($order_class_id, $coach_class_ids, true)) {
                continue;
            }

            $order_month = $order['pa_month'] ?? ($order['month'] ?? '');
            if ($order_month === '') {
                continue;
            }

            $order_end_month = $this->parseEndMonthNum($order_month);
            if ($order_end_month === null) {
                continue;
            }

            $order_year = isset($order['class_year']) ? (int) $order['class_year'] : 0;

            if ($order_year === $prev_year && $order_end_month === $prev_month_num) {
                return $order['pa_month'] ?? ($order['month'] ?? null);
            }
        }

        return null;
    }

    /**
     * @return array<int, array>
     */
    private function getAllClassesKeyedById(): array
    {
        if ($this->allClassesById !== null) {
            return $this->allClassesById;
        }

        $rows = EduClass::query()->get()->map(fn($m) => $m->getAttributes())->all();
        $this->allClassesById = $this->arrayServiceCommon->arrlist_change_key($rows, 'class_id');

        return $this->allClassesById;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findClassRowByName(string $class_name): ?array
    {
        $m = EduClass::query()->where('class_name', $class_name)->first();

        return $m ? $m->getAttributes() : null;
    }

    /**
     * @param  array<int, array>  $order_all
     */
    private function findNextMonthOrder(array $order_all, int $class_id, string $next_month, int $next_year): ?array
    {
        $class_id = (int) $class_id;
        $next_year = (int) $next_year;

        foreach ($order_all as $order) {
            $order_class_id = isset($order['class_id']) ? (int) $order['class_id'] : 0;
            $order_class_year = isset($order['class_year']) ? (int) $order['class_year'] : 0;
            $order_month = $order['month'] ?? '';

            if ($order_class_id === $class_id && $order_class_year === $next_year) {
                if ($this->isMonthMatched($order_month, $next_month)) {
                    return $order;
                }
            }
        }

        return null;
    }

    /**
     * 檢查月份是否匹配（支持跨月格式）
     *
     * @param string $order_month 訂單月份
     * @param string $target_month 目標月份
     * @return bool 是否匹配
     */
    private function isMonthMatched($order_month, $target_month)
    {
        // 處理跨月格式，例如 "7月-8月"
        if (strpos($order_month, '-') !== false) {
            $months = explode('-', $order_month);
            $cleaned_months = array_map(function ($m) {
                return trim(str_replace(['月', '月份'], '', $m));
            }, $months);

            // 將目標月份也清理一下
            $clean_target = trim(str_replace(['月', '月份'], '', $target_month));

            return in_array($clean_target, $cleaned_months);
        } else {
            // 單月格式
            $clean_order_month = trim(str_replace(['月', '月份'], '', $order_month));
            $clean_target = trim(str_replace(['月', '月份'], '', $target_month));

            return $clean_order_month === $clean_target;
        }
    }

    /**
     * @param  list<array{month: string, year: int}>  $period_list
     * @return array{orders: array<int, list<array>>, stats: array<string, mixed>}
     */
    private function queryOrdersByPeriods(array $student_ids, array $period_list, string $order_source): array
    {
        if ($student_ids === [] || $period_list === []) {
            return ['orders' => [], 'stats' => ['total' => 0, 'periods' => 0]];
        }

        $valid_sources = [self::ORDER_SOURCE_WHATSAPP, self::ORDER_SOURCE_WOOCOMMERCE];
        if (! in_array($order_source, $valid_sources, true)) {
            Log::debug("[StudentPaymentServiceCommon.queryOrdersByPeriods] Invalid order_source: {$order_source}");
            $order_source = self::ORDER_SOURCE_WHATSAPP;
        }

        $ids = array_map('intval', $student_ids);

        $query = EduOrder::query()
            ->whereIn('user_id', $ids)
            ->where('order_source', $order_source)
            ->where(function ($q) use ($period_list) {
                foreach ($period_list as $period) {
                    $q->orWhere(function ($w) use ($period) {
                        $w->where('month', $period['month'])
                            ->where('class_year', $period['year']);
                    });
                }
            })
            ->orderBy('user_id')
            ->orderByDesc('order_date');

        $orders = $query->get()->map(fn($m) => $m->getAttributes())->all();

        $grouped = [];
        foreach ($orders as $order) {
            $uid = (int) $order['user_id'];
            if (! isset($grouped[$uid])) {
                $grouped[$uid] = [];
            }
            $grouped[$uid][] = $order;
        }

        return [
            'orders' => $grouped,
            'stats' => [
                'total' => count($orders),
                'periods' => count($period_list),
            ],
        ];
    }

    private function calculateClassYearForWhatsapp(int|string $order_timestamp, string $pa_month): int
    {
        if (is_numeric($order_timestamp)) {
            $order_year = (int) date('Y', (int) $order_timestamp);
            $order_month_num = (int) date('n', (int) $order_timestamp);
        } else {
            $ts = strtotime((string) $order_timestamp);
            $order_year = (int) date('Y', $ts);
            $order_month_num = (int) date('n', $ts);
        }

        $class_month_numbers = $this->dateDayWeekService->get_array_month($pa_month);
        $last_class_month = (int) end($class_month_numbers);

        if ($order_month_num >= 10 && $last_class_month <= 4) {
            return $order_year + 1;
        }
        if ($order_month_num > $last_class_month) {
            return $order_year + 1;
        }

        return $order_year;
    }

    private function parseStartMonthNum(string $month_text): ?int
    {
        if ($month_text === '') {
            return null;
        }

        if (strpos($month_text, '-') !== false) {
            $parts = explode('-', $month_text);
            $start_text = trim($parts[0]);
            $month_num = (int) str_replace(['月', '月份'], '', $start_text);

            return ($month_num >= 1 && $month_num <= 12) ? $month_num : null;
        }

        $month_num = (int) str_replace(['月', '月份'], '', trim($month_text));

        return ($month_num >= 1 && $month_num <= 12) ? $month_num : null;
    }

    private function parseEndMonthNum(string $month_text): ?int
    {
        if ($month_text === '') {
            return null;
        }

        if (strpos($month_text, '-') !== false) {
            $parts = explode('-', $month_text);
            $end_text = trim($parts[count($parts) - 1]);
            $month_num = (int) str_replace(['月', '月份'], '', $end_text);

            return ($month_num >= 1 && $month_num <= 12) ? $month_num : null;
        }

        $month_num = (int) str_replace(['月', '月份'], '', trim($month_text));

        return ($month_num >= 1 && $month_num <= 12) ? $month_num : null;
    }
}
