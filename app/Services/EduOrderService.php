<?php

namespace App\Services;

use App\Models\EduOrder;
use App\Models\EduClass;
use App\Models\EduClassUser;
use App\Models\WpUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EduOrderService
{
    public function getClassList(int $page, string $kw = ''): array
    {
        $pagesize = 10;

        $query = EduClass::orderBy('class_id', 'desc');

        if (!empty($kw)) {
            $query->where('class_name', 'like', '%' . $kw . '%');
        }

        $list = $query->forPage($page, $pagesize)->get();

        $more = $list->count() === $pagesize;

        $results = [['id' => '', 'text' => '請選擇']];
        foreach ($list as $class) {
            $results[] = ['id' => $class->class_id, 'text' => $class->class_name];
        }

        return [
            'results' => $results,
            'pagination' => ['more' => $more],
        ];
    }

    public function getMonths(int $page, string $kw = '', int $classId = 0, ?string $classYear = null): array
    {
        $pagesize = 10;

        $query = EduClassUser::where('class_id', $classId)
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'desc');

        if (!empty($kw)) {
            $query->where('month', 'like', '%' . $kw . '%');
        }

        if (!empty($classYear)) {
            $query->where('class_year', $classYear);
        }

        $list = $query->forPage($page, $pagesize)->get();

        // Only keep months that are truly active (have students/teachers/transfers)
        $activeMonths = [];
        foreach ($list as $row) {
            $hasStudents = !empty($row->student);
            $hasTeachers = !empty($row->teacher);
            $hasTransfers = !empty($row->student_transfer);

            if ($hasStudents || $hasTeachers || $hasTransfers) {
                if (!in_array($row->month, $activeMonths)) {
                    $activeMonths[] = $row->month;
                }
            }
        }

        usort($activeMonths, function ($a, $b) {
            return $this->extractFirstMonthNumber($b) - $this->extractFirstMonthNumber($a);
        });

        $more = $list->count() === $pagesize;

        $results = [['id' => '', 'text' => '請選擇']];
        foreach ($activeMonths as $month) {
            $results[] = ['id' => $month, 'text' => $month];
        }

        return [
            'results' => $results,
            'pagination' => ['more' => $more],
        ];
    }

    public function getMonthsForRenew(int $page, string $kw = '', int $classId = 0, ?string $classYear = null): array
    {
        $data = $this->getMonths($page, $kw, $classId, $classYear);

        $results = $data['results'];

        if (isset($results[1])) {
            $latestMonth = $results[1]['id'];
            $nextMonth = $this->getStringNextMonth($latestMonth);

            array_splice($results, 1, 0, [['id' => $nextMonth, 'text' => $nextMonth]]);
        }

        return [
            'results' => $results,
            'pagination' => $data['pagination'],
        ];
    }

    public function addOrUpdateOrder(?int $orderId, int $studentId, array $data): EduOrder
    {
        $data['order_date'] = strtotime($data['order_date']);   // convert Y-m-d → Unix timestamp
        $data['created'] = time();
        $data['order_source'] = EduOrder::SOURCE_MANUAL;

        // Atomic: the order row and the class-user roster must move together.
        // If the roster sync throws, the order write rolls back, so we never
        // leave an "order exists but student not in class" state.
        return DB::transaction(function () use ($orderId, $studentId, $data) {
            if ($orderId) {
                EduOrder::where('id', $orderId)->update($data);
                $order = EduOrder::find($orderId);
            } else {
                $data['user_id'] = $studentId;
                $order = EduOrder::create($data);
            }

            $this->syncStudentIntoClassUser($data, $studentId);

            return $order;
        });
    }

    public function orderAddForRenew(array $data, int $studentId): array
    {
        $data['amount'] = str_replace(',', '', $data['amount'] ?? '');

        $data['order_date'] = strtotime($data['order_date']);
        $data['created'] = time();
        $data['order_source'] = EduOrder::SOURCE_MANUAL;
        $data['user_id'] = $studentId;

        // Two-layer protection against concurrent duplicate renewals:
        //   1) Fast pre-check for friendly "already exists" message.
        //   2) DB unique index on (user_id, class_id, month, class_year) is the
        //      authoritative guard — catch the 23000 violation for the race
        //      where two requests pass the pre-check simultaneously.
        try {
            return DB::transaction(function () use ($data, $studentId) {
                $exists = EduOrder::where('class_id', $data['class_id'])
                    ->where('month', $data['month'])
                    ->where('class_year', $data['class_year'])
                    ->where('user_id', $studentId)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    return ['status' => false, 'message' => '續費已存在!'];
                }

                EduOrder::create($data);

                $this->syncStudentIntoClassUser($data, $studentId);

                return ['status' => true, 'message' => 'Success'];
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // SQLSTATE 23000 = integrity constraint violation (unique key).
            if ($e->getCode() === '23000') {
                return ['status' => false, 'message' => '續費已存在!'];
            }

            Log::error('orderAddForRenew failed', [
                'user_id' => $studentId,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getOrderById(int $orderId): ?array
    {
        $order = EduOrder::find($orderId);
        return $order ? $order->toArray() : null;
    }

    public function getLastMonthAndLastClass(int $userId): array
    {
        $allOrders = [];

        $manualOrders = EduOrder::where('user_id', $userId)
            ->whereNotNull('order_date')
            ->where('order_date', '>', 0)
            ->get();

        foreach ($manualOrders as $order) {
            $allOrders[] = [
                'order_date' => $order->order_date,
                'class_id' => $order->class_id,
                'month' => $order->month,
                'class_year' => $order->class_year,
                'source' => 'manual',
            ];
        }

        $wooOrders = $this->getUserWooCommerceOrders($userId);
        foreach ($wooOrders as $order) {
            $orderDate = !empty($order['_paid_date'])
                ? strtotime($order['_paid_date'])
                : strtotime($order['post_date'] ?? '');

            if ($orderDate > 0) {
                $allOrders[] = [
                    'order_date' => $orderDate,
                    'class_id' => $order['class_id'] ?? null,
                    'month' => $order['month'] ?? null,
                    'class_year' => $order['class_year'] ?? null,
                    'source' => 'woocommerce',
                ];
            }
        }

        if (empty($allOrders)) {
            return ['last_month' => [], 'last_class' => []];
        }

        usort($allOrders, fn($a, $b) => $b['order_date'] - $a['order_date']);

        $latest = $allOrders[0];
        $lastMonth = [];
        $lastClass = [];

        if (!empty($latest['class_id'])) {
            $classUser = EduClassUser::where('class_id', $latest['class_id'])
                ->where('month', $latest['month'])
                ->where('class_year', $latest['class_year'])
                ->first();

            $lastMonth = $classUser
                ? $classUser->toArray()
                : [
                    'class_id' => $latest['class_id'],
                    'month' => $latest['month'],
                    'class_year' => $latest['class_year'],
                ];

            $class = EduClass::find($latest['class_id']);
            $lastClass = $class ? $class->toArray() : [];
        }

        return ['last_month' => $lastMonth, 'last_class' => $lastClass];
    }

    public function getOrderSummary(?string $daterange): array
    {
        [$from, $to] = $this->parseDateRange($daterange);

        $list = EduOrder::whereDateRange($from, $to)->get();

        $amount = '0';
        $refund = '0';
        $userIds = [];

        foreach ($list as $order) {
            $userIds[] = $order->user_id;

            if ($order->woo_status === EduOrder::STATUS_COMPLETED) {
                $amount = bcadd($amount, (string)$order->amount, 2);
            }

            if (trim($order->woo_status ?? '') === EduOrder::STATUS_REFUNDED) {
                $refund = bcadd($refund, (string)$order->amount, 2);
            }
        }

        $userIds = array_unique(array_filter($userIds));
        $users = WpUser::whereIn('ID', $userIds)
            ->get()
            ->keyBy('ID')
            ->map(fn($u) => [
                'ID' => $u->ID,
                'first_name' => $u->display_name,
                'display_name' => $u->display_name,
                'user_login' => $u->user_login,
            ])
            ->toArray();

        return [
            'list' => $list->toArray(),
            'amount' => $amount,
            'refund' => $refund,
            'users' => $users,
        ];
    }

    public function processOrderRefund(int $orderId, array $data): array
    {
        $order = $this->getOrderWithClassName($orderId);

        if (!$order) {
            return ['status' => false, 'message' => '訂單不存在'];
        }
        if (empty($data['refund_reason']) && empty($data['refund_reason2'])) {
            return ['status' => false, 'message' => '退款原因不能為空!'];
        }
        if (empty($data['refund_fee'])) {
            return ['status' => false, 'message' => '退款金額不能為空!'];
        }
        if (empty($data['refund_date'])) {
            return ['status' => false, 'message' => '退款日期不能為空!'];
        }
        if ($data['refund_fee'] > $order['amount']) {
            return ['status' => false, 'message' => '退款金額不能超過支付金額!'];
        }

        if (!empty($data['refund_reason2'])) {
            $data['refund_reason'] = $data['refund_reason2'];
        }
        unset($data['refund_reason2']);

        $data['refund_date'] = strtotime($data['refund_date']);

        // Atomic: refund fields + roster removal must commit together.
        // Otherwise we can end up "refunded but still in class".
        DB::transaction(function () use ($orderId, $data, $order) {
            EduOrder::where('id', $orderId)->update($data);

            $this->removeStudentFromClass($order);
        });

        return ['status' => true, 'message' => 'Success'];
    }

    public function getOrderWithClassName(int $orderId): ?array
    {
        $order = EduOrder::find($orderId);

        if (!$order) {
            return null;
        }

        $class = $order->class_id
            ? EduClass::find($order->class_id)
            : null;

        $result = $order->toArray();
        $result['class_name'] = $class?->class_name ?? '未知課程';

        return $result;
    }

    public function getOrderAndNextMonth(?int $orderId, ?int $classId = null, ?string $currentMonth = null, ?string $currentYear = null): array
    {
        if ($orderId) {
            $order = EduOrder::find($orderId);
            if (!$order) {
                return [[], '', (string)date('Y')];
            }

            $class = $order->class_id ? EduClass::find($order->class_id) : null;
            $orderArr = $order->toArray();
            $orderArr['class_name'] = $class?->class_name ?? '';

            [$nextMonth, $nextYear] = $this->getNextYearMonth($order->class_year, $order->month);
        } else {
            $class = $classId ? EduClass::find($classId) : null;
            $orderArr = [
                'class_id' => $classId,
                'class_name' => $class?->class_name ?? '',
            ];

            $year = $currentYear ?: date('Y');
            [$nextMonth, $nextYear] = $this->getNextYearMonth($year, $currentMonth ?? '');
        }

        return [$orderArr, $nextMonth, $nextYear];
    }

    private function syncStudentIntoClassUser(array $data, int $studentId): void
    {
        $classUser = EduClassUser::where('class_id', $data['class_id'])
            ->where('month', $data['month'])
            ->where('class_year', $data['class_year'])
            ->first();

        if ($classUser) {
            $students = $classUser->student ?? [];
            $transfers = $classUser->student_transfer ?? [];

            $alreadyIn = in_array($studentId, $students) || in_array($studentId, $transfers);

            if (!$alreadyIn) {
                $updated = array_unique(array_merge([$studentId], $students));
                $classUser->student = $updated;
                $classUser->save();
            }
        } else {
            $newRecord = [
                'class_id' => $data['class_id'],
                'month' => $data['month'],
                'class_year' => $data['class_year'],
                'student' => [$studentId],
                'sort' => $this->yearMonthSort($data['class_year'], $data['month']),
            ];

            $prev = EduClassUser::where('class_id', $data['class_id'])
                ->where('sort', '<', $newRecord['sort'])
                ->orderBy('sort', 'desc')
                ->first();

            if ($prev) {
                $newRecord['teacher'] = $prev->getRawOriginal('teacher');
                $newRecord['class_exam'] = $prev->getRawOriginal('class_exam');
            }

            EduClassUser::create($newRecord);
        }
    }

    private function removeStudentFromClass(array $order): void
    {
        if (empty($order['user_id']) || empty($order['class_id']) || empty($order['month']) || empty($order['class_year'])) {
            Log::warning('removeStudentFromClass: incomplete order data', $order);
            return;
        }

        $classUser = EduClassUser::where('class_id', $order['class_id'])
            ->where('month', $order['month'])
            ->where('class_year', $order['class_year'])
            ->first();

        if (!$classUser) {
            Log::warning('removeStudentFromClass: class_user record not found', [
                'class_id' => $order['class_id'],
                'month' => $order['month'],
                'class_year' => $order['class_year'],
            ]);
            return;
        }

        $students = $classUser->student ?? [];

        $key = array_search($order['user_id'], $students);
        if ($key === false) {
            Log::info('removeStudentFromClass: student not in class', [
                'user_id' => $order['user_id'],
                'class_id' => $order['class_id'],
            ]);
            return;
        }

        unset($students[$key]);
        $classUser->student = array_values($students);
        $classUser->save();

        Log::info('removeStudentFromClass: student removed', [
            'user_id' => $order['user_id'],
            'class_id' => $order['class_id'],
            'month' => $order['month'],
        ]);
    }

    public function getWooOrderList(
        ?int $userId = null,
        string $dateStart = '',
        array $orderIds = [],
        bool $removeExtraFields = true,
        bool $dropInvalidItems = false
    ): array {
        $rows = $this->buildWooOrdersQuery($userId, $dateStart, $orderIds)->get();

        $orders = $this->groupWooOrdersByOrderId($rows);

        if ($removeExtraFields) {
            $this->removeWooExtraFields($orders);
        }

        $this->enrichWooOrders($orders, $userId, $dropInvalidItems);

        return $orders;
    }

    private function buildWooOrdersQuery(?int $userId, string $dateStart, array $orderIds)
    {
        $dateStart = empty($dateStart)
            ? now()->subDays(90)->format('Y-m-d H:i:s')
            : date('Y-m-d H:i:s', strtotime($dateStart));

        $query = DB::table('woocommerce_order_items as oi')
            ->join('posts as p', 'p.ID', '=', 'oi.order_id')
            ->join('postmeta as pm', 'pm.post_id', '=', 'oi.order_id')
            ->whereIn('p.post_status', ['wc-completed', 'wc-refunded'])
            ->where('p.post_date', '>', $dateStart)
            ->orderByDesc('p.post_date')
            ->select([
                'oi.*',
                'p.post_date',
                'p.post_status',
                'pm.meta_key',
                'pm.meta_value',
            ]);

        if ($userId !== null) {
            $query->whereExists(function ($subQuery) use ($userId) {
                $subQuery->select(DB::raw(1))
                    ->from('postmeta as pm_user')
                    ->whereColumn('pm_user.post_id', 'oi.order_id')
                    ->where('pm_user.meta_key', '_customer_user')
                    ->where('pm_user.meta_value', (string) $userId);
            });
        }

        if (!empty($orderIds)) {
            $query->whereIn('oi.order_id', $orderIds);
        }

        return $query;
    }

    private function groupWooOrdersByOrderId($rows): array
    {
        $orders = [];

        foreach ($rows as $row) {
            $row = (array) $row;

            $orderId = $row['order_id'];
            $metaKey = $row['meta_key'] ?? null;
            $metaValue = $row['meta_value'] ?? null;

            unset($row['meta_key'], $row['meta_value']);

            if (!isset($orders[$orderId])) {
                $orders[$orderId] = $row;
            }

            if ($metaKey !== null) {
                $orders[$orderId][$metaKey] = $metaValue;
            }
        }

        return $orders;
    }

    private function removeWooExtraFields(array &$orders): void
    {
        $removeFields = [
            'order_item_id', 'order_item_type', 'post_author', 'post_content', 'post_title',
            'post_excerpt', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged',
            'post_modified', 'post_modified_gmt', 'post_content_filtered', 'post_parent', 'guid',
            'menu_order', 'post_mime_type', 'comment_count', 'meta_id', '_customer_ip_address',
            '_customer_user_agent', '_created_via', '_cart_hash', '_download_permissions_granted',
            '_recorded_sales', '_recorded_coupon_usage_counts', '_new_order_email_sent',
            '_order_stock_reduced', '_cart_discount', '_cart_discount_tax', '_order_shipping',
            '_order_shipping_tax', '_order_tax', '_order_version', '_prices_include_tax',
            '_shipping_address_index', '_billing_note', 'is_vat_exempt', 'whatsapp_notifications',
            'sms_notifications', 'sms_notifications_time', 'whatsapp_notifications_time',
            '_wc_order_attribution_source_type', '_wc_order_attribution_utm_source',
            '_wc_order_attribution_session_entry', '_wc_order_attribution_session_start_time',
            '_wc_order_attribution_session_pages', '_wc_order_attribution_session_count',
            '_wc_order_attribution_user_agent', '_wc_order_attribution_device_type', '_ga_tracked',
            '_edit_lock', '_edit_last', '_billing_birthdate', '_billing_gender', '_billing_age',
            '_billing_school', '_billing_swimlevel', '_billing_swimtype1', '_billing_swimtype2',
            '_billing_swimtype3', '_billing_contactname', '_billing_contactphone', '_order_key',
            '_billing_address_index',
        ];

        foreach ($orders as &$order) {
            unset($order['meta_key'], $order['meta_value']);

            foreach ($removeFields as $field) {
                unset($order[$field]);
            }
        }
        unset($order);
    }

    private function enrichWooOrders(array &$orders, ?int $userId, bool $dropInvalidItems): void
    {
        $classNames = [];

        foreach ($orders as $key => &$order) {
            $parsed = $this->parseWooOrderItem($order['order_item_name'] ?? '');

            if ($parsed === null) {
                if ($dropInvalidItems) {
                    unset($orders[$key]);
                }
                continue;
            }

            $order['_parsed_month'] = $parsed['month'];
            $order['_parsed_class_name'] = $parsed['class_name'];

            $classNames[] = $parsed['class_name'];
        }
        unset($order);

        if (empty($classNames)) {
            return;
        }

        $classMap = EduClass::query()
            ->whereIn('class_name', array_unique($classNames))
            ->get(['class_id', 'class_name'])
            ->keyBy('class_name');

        foreach ($orders as &$order) {
            if (!isset($order['_parsed_month'], $order['_parsed_class_name'])) {
                continue;
            }

            $className = $order['_parsed_class_name'];
            $month = $order['_parsed_month'];
            $class = $classMap->get($className);

            $order['month'] = $month;
            $order['class_name'] = $className;
            $order['class_id'] = $class?->class_id;
            $order['class_year'] = $this->getClassYear($order['post_date'] ?? '', $month);
            $order['user_id'] = isset($order['_customer_user'])
                ? (int) $order['_customer_user']
                : $userId;

            unset($order['_parsed_month'], $order['_parsed_class_name']);
        }
        unset($order);
    }

    private function parseWooOrderItem(string $orderItemName): ?array
    {
        if (
            strpos($orderItemName, ' - ') === false ||
            mb_strpos($orderItemName, '逢') === false
        ) {
            return null;
        }

        $dashPos = strpos($orderItemName, ' - ');
        $productName = substr($orderItemName, 0, $dashPos);
        $rest = substr($orderItemName, $dashPos + 3);

        $fenPos = mb_strpos($rest, '逢');
        $month = trim(mb_substr($rest, 0, $fenPos), " \t\n\r\v\f,");
        $time = trim(mb_substr($rest, $fenPos + 1), " \t\n\r\v\f,");

        return [
            'month' => $month,
            'class_name' => $productName . $time,
        ];
    }

    private function extractFirstMonthNumber(string $month): int
    {
        if (preg_match('/^(\d+)月/', $month, $m)) {
            return (int)$m[1];
        }
        return 0;
    }

    private function getStringNextMonth(string $month): string
    {
        if (preg_match('/^(\d+)月-(\d+)月$/', $month, $m)) {
            $end = (int)$m[2];
            $start = $end + 1;
            $endNext = $end + 2;

            if ($start > 12) {
                $start = 1;
                $endNext = 2;
            } elseif ($endNext > 12) {
                $endNext = 1;
            }

            return "{$start}月-{$endNext}月";
        }

        if (preg_match('/^(\d+)月$/', $month, $m)) {
            $next = (int)$m[1] + 1;
            if ($next > 12) {
                $next = 1;
            }
            return "{$next}月";
        }

        return $month;
    }

    private function yearMonthSort(string $classYear, string $month): int
    {
        $firstMonth = $this->extractFirstMonthNumber($month);
        return (int)($classYear . str_pad((string)$firstMonth, 2, '0', STR_PAD_LEFT));
    }

    private function getNextYearMonth(string $classYear, string $month): array
    {
        $nextMonth = $this->getStringNextMonth($month);
        $nextYear = (int)$classYear;

        $currentFirst = $this->extractFirstMonthNumber($month);
        $nextFirst = $this->extractFirstMonthNumber($nextMonth);

        if ($nextFirst < $currentFirst) {
            $nextYear++;
        }

        return [$nextMonth, (string)$nextYear];
    }

    private function getClassYear(string $postDate, string $paMonth): string
    {
        $postYear = (int)date('Y', strtotime($postDate));
        $postMonth = (int)date('n', strtotime($postDate));
        $firstMonth = $this->extractFirstMonthNumber($paMonth);

        if ($postMonth >= 10 && $firstMonth >= 1 && $firstMonth <= 4) {
            return (string)($postYear + 1);
        }

        return (string)$postYear;
    }

    private function parseDateRange(?string $daterange): array
    {
        if ($daterange) {
            $parts = explode(' - ', $daterange);
            return [
                strtotime(trim($parts[0])),
                strtotime(trim($parts[1])),
            ];
        }

        return [
            strtotime(date('Y-m-01')),
            strtotime(date('Y-m-d')),
        ];
    }


    public function getWhatsappOrderList(int $classYear, int $classMonth): array
    {
        $prevMonth = $classMonth === 1 ? 12 : $classMonth - 1;
        $nextMonth = $classMonth === 12 ? 1 : $classMonth + 1;

        $months = [
            "{$classMonth}月",
            "{$prevMonth}月-{$classMonth}月",
            "{$classMonth}月-{$nextMonth}月",
        ];

        return EduOrder::query()
            ->where('order_source', EduOrder::SOURCE_MANUAL)
            ->where('class_year', $classYear)
            ->whereIn('month', $months)
            ->orderByDesc('id')
            ->get();
    }
}