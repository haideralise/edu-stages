<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>私人課堂 Private Classes</title>
    <style>
        body { font-family: sans-serif; max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        h1 { font-size: 1.6rem; margin-bottom: 1rem; }
        .coach-select { margin-bottom: 1.5rem; display: flex; gap: 10px; align-items: center; }
        select { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; min-width: 200px; }
        button { padding: 6px 14px; border: none; border-radius: 4px; cursor: pointer; background: #0070f3; color: #fff; }
        button:hover { background: #0051a2; }
        button.danger { background: #dc3545; }
        button.danger:hover { background: #bd2130; }
        button.success-btn { background: #28a745; }
        button.success-btn:hover { background: #1e7e34; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; font-size: 0.85rem; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; font-weight: bold; }
        input[type="text"], input[type="date"], input[type="time"], input[type="number"] {
            padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; width: 100%; box-sizing: border-box;
        }
        .msg { margin: 10px 0; padding: 8px 12px; border-radius: 4px; display: none; }
        .msg.success { background: #d4edda; color: #155724; }
        .msg.error { background: #f8d7da; color: #721c24; }
        .toolbar { margin-bottom: 10px; display: flex; gap: 8px; }
        .back-link { margin-bottom: 1rem; }
        .back-link a { color: #0070f3; }
    </style>
</head>
<body>
    <h1>私人課堂管理 Private Classes</h1>

    <div class="back-link"><a href="{{ url('/edu/coach') }}">&larr; 返回教練列表 Back to Coach List</a></div>

    <form class="coach-select" method="GET" action="{{ url('/edu/coach/private-classes') }}">
        <label for="coachSelect">選擇教練 Select Coach:</label>
        <select id="coachSelect" name="coach_id">
            <option value="0">-- 請選擇 Please select --</option>
            @foreach($all_coaches as $coach)
            <option value="{{ $coach['ID'] }}" {{ (int)$coach_id === (int)$coach['ID'] ? 'selected' : '' }}>
                {{ $coach['display_name'] }}
            </option>
            @endforeach
        </select>
        <button type="submit">載入 Load</button>
    </form>

    @if($coach_id > 0)
    <h2>{{ $coach_name }} 的私人課堂 (ID: {{ $coach_id }})</h2>

    <div id="globalMsg" class="msg"></div>

    <div class="toolbar">
        <button type="button" id="addRowBtn">+ 新增一行 Add Row</button>
        <button type="button" class="success-btn" id="saveBtn">儲存 Save All</button>
    </div>

    <table id="bookingsTable">
        <thead>
            <tr>
                <th>學生姓名 Student Name</th>
                <th>電話 Phone</th>
                <th>地區 District</th>
                <th>泳池 Pool</th>
                <th>其他地點 Other Location</th>
                <th>上課日期 Class Date</th>
                <th>開始時間 Start Time</th>
                <th>結束時間 End Time</th>
                <th>比例 Ratio</th>
                <th>類型 Type</th>
                <th>費用 Fee</th>
                <th>狀態 Status</th>
                <th>付款日期 Payment Date</th>
                <th>退款日期 Refund Date</th>
                <th>出席 Attendance</th>
                <th>備注 Remark</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody id="bookingsBody">
            @foreach($bookings_from_db as $booking)
            <tr>
                <td><input type="text" name="student_name" value="{{ $booking['student_name'] ?? '' }}"></td>
                <td><input type="text" name="student_phone" value="{{ $booking['student_phone'] ?? '' }}"></td>
                <td><input type="text" name="district" value="{{ $booking['district'] ?? '' }}"></td>
                <td><input type="text" name="pool" value="{{ $booking['pool'] ?? '' }}"></td>
                <td><input type="text" name="other_location" value="{{ $booking['other_location'] ?? '' }}"></td>
                <td><input type="date" name="class_date" value="{{ $booking['class_date'] ?? '' }}"></td>
                <td><input type="time" name="class_time" value="{{ $booking['class_time'] ?? '' }}"></td>
                <td><input type="time" name="class_end_time" value="{{ $booking['class_end_time'] ?? '' }}"></td>
                <td><input type="text" name="ratio" value="{{ $booking['ratio'] ?? '' }}"></td>
                <td><input type="text" name="type" value="{{ $booking['type'] ?? '' }}"></td>
                <td><input type="number" name="fee" step="0.01" min="0" value="{{ $booking['fee'] ?? 0 }}"></td>
                <td>
                    <select name="status">
                        @foreach(['Pending','Paid','Refunded','Cancelled'] as $s)
                        <option value="{{ $s }}" {{ ($booking['status'] ?? 'Pending') === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </td>
                <td><input type="date" name="payment_date" value="{{ $booking['payment_date'] ?? '' }}"></td>
                <td><input type="date" name="refund_date" value="{{ $booking['refund_date'] ?? '' }}"></td>
                <td><input type="text" name="attendance" value="{{ $booking['attendance'] ?? '' }}"></td>
                <td><input type="text" name="remark" value="{{ $booking['remark'] ?? '' }}"></td>
                <td><button type="button" class="danger del-btn">刪除 Del</button></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const saveUrl = '{{ url("/edu/coach/private-classes") }}';
        const coachId = {{ $coach_id }};

        function emptyRow() {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" name="student_name" value=""></td>
                <td><input type="text" name="student_phone" value=""></td>
                <td><input type="text" name="district" value=""></td>
                <td><input type="text" name="pool" value=""></td>
                <td><input type="text" name="other_location" value=""></td>
                <td><input type="date" name="class_date" value=""></td>
                <td><input type="time" name="class_time" value=""></td>
                <td><input type="time" name="class_end_time" value=""></td>
                <td><input type="text" name="ratio" value=""></td>
                <td><input type="text" name="type" value=""></td>
                <td><input type="number" name="fee" step="0.01" min="0" value="0"></td>
                <td>
                    <select name="status">
                        <option value="Pending" selected>Pending</option>
                        <option value="Paid">Paid</option>
                        <option value="Refunded">Refunded</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </td>
                <td><input type="date" name="payment_date" value=""></td>
                <td><input type="date" name="refund_date" value=""></td>
                <td><input type="text" name="attendance" value=""></td>
                <td><input type="text" name="remark" value=""></td>
                <td><button type="button" class="danger del-btn">刪除 Del</button></td>
            `;
            tr.querySelector('.del-btn').addEventListener('click', function () {
                tr.remove();
            });
            return tr;
        }

        document.getElementById('addRowBtn').addEventListener('click', function () {
            document.getElementById('bookingsBody').appendChild(emptyRow());
        });

        document.querySelectorAll('.del-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.closest('tr').remove();
            });
        });

        function showMsg(msg, isError) {
            const el = document.getElementById('globalMsg');
            el.textContent = msg;
            el.className = 'msg ' + (isError ? 'error' : 'success');
            el.style.display = 'block';
            setTimeout(() => { el.style.display = 'none'; }, 5000);
        }

        document.getElementById('saveBtn').addEventListener('click', async function () {
            const rows = document.querySelectorAll('#bookingsBody tr');
            const bookings = [];
            rows.forEach(function (row) {
                const val = (name) => row.querySelector('[name="' + name + '"]')?.value || '';
                bookings.push({
                    student_name: val('student_name'),
                    student_phone: val('student_phone'),
                    district: val('district'),
                    pool: val('pool'),
                    other_location: val('other_location'),
                    class_date: val('class_date') || null,
                    class_time: val('class_time'),
                    class_end_time: val('class_end_time'),
                    ratio: val('ratio'),
                    type: val('type'),
                    fee: parseFloat(val('fee')) || 0,
                    status: val('status'),
                    payment_date: val('payment_date') || null,
                    refund_date: val('refund_date') || null,
                    attendance: val('attendance'),
                    remark: val('remark'),
                });
            });

            try {
                const res = await fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ coach_id: coachId, bookings: bookings }),
                });
                const json = await res.json();
                if (res.ok) {
                    showMsg('儲存成功 Saved successfully', false);
                } else {
                    showMsg(json.message || '儲存失敗 Save failed', true);
                }
            } catch (err) {
                showMsg('網絡錯誤 Network error', true);
            }
        });
    </script>
    @else
    <p style="color:#888;">請先選擇教練 Please select a coach above.</p>
    @endif
</body>
</html>
