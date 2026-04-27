@extends('layouts.app')

@section('title', 'Payment History')

@section('content')
<h1 class="text-2xl font-bold mb-4">Payment History</h1>

@if (empty($allOrders))
    <p class="text-gray-500">No payment records found.</p>
@else
    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Renewal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($allOrders as $entry)
                    @php
                        $order = $entry['order'] ?? [];
                        $className = $entry['class_name'] ?? '';
                        $paMonth = $entry['pa_month'] ?? ($entry['month'] ?? '');
                        $amount = $order['_order_total'] ?? ($entry['amount'] ?? 0);
                        $date = $order['post_date'] ?? ($order['order_date'] ?? '');
                        $source = isset($order['order_source']) ? 'WhatsApp' : 'WooCommerce';
                        $renewText = $entry['renew_button_text'] ?? '';
                        $renewCurrent = $entry['renew_current'] ?? 0;
                    @endphp
                    <tr class="{{ $renewCurrent ? 'bg-blue-50' : '' }}">
                        <td class="px-4 py-2 text-sm">{{ $className }}</td>
                        <td class="px-4 py-2 text-sm">{{ $paMonth }}</td>
                        <td class="px-4 py-2 text-sm">${{ number_format((float) $amount, 2) }}</td>
                        <td class="px-4 py-2 text-sm">{{ $date ? date('Y-m-d', strtotime($date)) : '' }}</td>
                        <td class="px-4 py-2 text-sm">{{ $source }}</td>
                        <td class="px-4 py-2 text-sm">
                            @if ($renewText)
                                @php
                                    $renewColor = match ($renewText) {
                                        '已續費' => 'text-green-600 bg-green-50',
                                        '待續費' => 'text-yellow-600 bg-yellow-50',
                                        '不續費' => 'text-red-600 bg-red-50',
                                        default => 'text-gray-600 bg-gray-50',
                                    };
                                @endphp
                                <span class="inline-block px-2 py-1 rounded text-xs font-medium {{ $renewColor }}">
                                    {{ $renewText }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
