@extends('layouts.app')

@section('title', 'Health Records')

@section('content')
<div>
    <h1 class="text-xl font-bold mb-4">Health / Growth Records</h1>

    {{-- BMI Table --}}
    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" id="bmi-table">
            <thead class="bg-gray-50">
                <tr>
                    @if ($isAdmin)
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Student</th>
                    @endif
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Height (cm)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Weight (kg)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">HC (cm)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">BMI</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Health</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200" id="bmi-tbody">
                @forelse ($records as $record)
                <tr data-id="{{ $record->id }}">
                    @if ($isAdmin)
                        <td class="px-4 py-3 text-sm font-medium">{{ $record->student_name }}</td>
                    @endif
                    <td class="px-4 py-3 text-sm">{{ number_format($record->height, 2) }}</td>
                    <td class="px-4 py-3 text-sm">{{ number_format($record->weight, 2) }}</td>
                    <td class="px-4 py-3 text-sm">{{ $record->hc ? number_format($record->hc, 2) : '-' }}</td>
                    <td class="px-4 py-3 text-sm">{{ date('Y-m-d', $record->date) }}</td>
                    <td class="px-4 py-3 text-sm font-medium">{{ number_format($record->bmi, 2) }}</td>
                    <td class="px-4 py-3 text-sm">
                        @php
                            $categoryMap = [
                                'underweight' => ['label' => 'Underweight', 'color' => 'text-yellow-600'],
                                'normal'      => ['label' => 'Normal',      'color' => 'text-green-600'],
                                'overweight'  => ['label' => 'Overweight',  'color' => 'text-orange-600'],
                                'obese'       => ['label' => 'Obese',       'color' => 'text-red-600'],
                            ];
                            $cat = $categoryMap[$record->category] ?? ['label' => $record->category, 'color' => 'text-gray-600'];
                        @endphp
                        <span class="font-medium {{ $cat['color'] }}">{{ $cat['label'] }}</span>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <a href="javascript:void(0)"
                           onclick="openEditModal({{ $record->id }})"
                           class="text-blue-600 hover:underline">Edit</a>
                        <a href="javascript:void(0)"
                           onclick="openDeleteModal({{ $record->id }})"
                           class="text-red-600 hover:underline ml-2">Delete</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $isAdmin ? 8 : 7 }}" class="px-4 py-6 text-center text-gray-400">No records found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Add button --}}
    <div class="flex justify-end mt-4">
        <button onclick="openAddModal()"
                class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 text-sm">
            Add New
        </button>
    </div>

    {{-- Growth Chart Section --}}
    <div class="mt-8">
        <h2 class="text-lg font-bold mb-3">Growth Charts</h2>
        <ul class="list-disc list-inside text-sm text-blue-600 space-y-1">
            <li><a href="#" class="hover:underline">Height</a></li>
            <li><a href="#" class="hover:underline">Weight</a></li>
            <li><a href="#" class="hover:underline">BMI</a></li>
            <li><a href="#" class="hover:underline">Head Circumference</a></li>
        </ul>
    </div>
</div>

{{-- Add/Edit Modal --}}
<div id="bmi-modal" class="fixed inset-0 z-50 hidden items-center justify-center" style="background:rgba(0,0,0,0.4)">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 id="modal-title" class="text-lg font-bold">Add New</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <form id="bmi-form" onsubmit="submitForm(event)">
            <div class="px-6 py-4 space-y-4">
                <div id="modal-errors" class="hidden bg-red-50 text-red-600 text-sm p-3 rounded"></div>
                <input type="hidden" id="form-id" value="">
                @if ($isAdmin)
                <div id="student-field">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Student</label>
                    <select id="form-user-id" class="w-full border rounded px-3 py-2">
                        <option value="">-- Select Student --</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->ID }}">{{ $student->display_name }} ({{ $student->user_login }})</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Height</label>
                    <input type="number" id="form-height" step="0.1" min="30" max="250"
                           class="w-full border rounded px-3 py-2" placeholder="Enter number in cm, e.g. 100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Weight</label>
                    <input type="number" id="form-weight" step="0.1" min="1" max="300"
                           class="w-full border rounded px-3 py-2" placeholder="Enter number in kg, e.g. 35">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Head Circumference</label>
                    <input type="number" id="form-hc" step="0.1" min="20" max="100"
                           class="w-full border rounded px-3 py-2" placeholder="Enter number in cm, e.g. 52">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input type="date" id="form-date"
                           class="w-full border rounded px-3 py-2" placeholder="YYYY-MM-DD">
                </div>
            </div>
            <div class="px-6 py-4 border-t flex justify-end">
                <button type="submit"
                        class="text-white px-6 py-2 rounded font-medium"
                        style="background: linear-gradient(135deg, #34d399, #10b981);">
                    Save
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div id="delete-modal" class="fixed inset-0 z-50 hidden items-center justify-center" style="background:rgba(0,0,0,0.4)">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-sm mx-4">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-bold">Confirm</h3>
        </div>
        <div class="px-6 py-6">
            <p class="text-gray-700">Are you sure you want to delete this record?</p>
        </div>
        <div class="px-6 py-4 border-t flex justify-end gap-3">
            <button onclick="closeDeleteModal()"
                    class="px-4 py-2 border rounded text-gray-600 hover:bg-gray-50">
                Cancel
            </button>
            <button onclick="confirmDelete()"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Confirm
            </button>
        </div>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const isAdmin = {{ $isAdmin ? 'true' : 'false' }};
let deleteId = null;

function openAddModal() {
    document.getElementById('modal-title').textContent = 'Add New';
    document.getElementById('form-id').value = '';
    document.getElementById('form-height').value = '';
    document.getElementById('form-weight').value = '';
    document.getElementById('form-hc').value = '';
    document.getElementById('form-date').value = '';
    document.getElementById('modal-errors').classList.add('hidden');
    if (isAdmin) {
        document.getElementById('form-user-id').value = '';
        document.getElementById('student-field').style.display = '';
    }
    const modal = document.getElementById('bmi-modal');
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
}

function openEditModal(id) {
    document.getElementById('modal-title').textContent = 'Edit';
    document.getElementById('modal-errors').classList.add('hidden');

    fetch(`/account/bmi/${id}`, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        }
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('form-id').value = data.id;
        document.getElementById('form-height').value = data.height;
        document.getElementById('form-weight').value = data.weight;
        document.getElementById('form-hc').value = data.hc || '';
        document.getElementById('form-date').value = data.date_formatted;
        if (isAdmin) {
            document.getElementById('form-user-id').value = data.user_id;
            document.getElementById('student-field').style.display = 'none';
        }
        const modal = document.getElementById('bmi-modal');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    });
}

function closeModal() {
    const modal = document.getElementById('bmi-modal');
    modal.classList.add('hidden');
    modal.style.display = 'none';
}

function submitForm(e) {
    e.preventDefault();

    const id = document.getElementById('form-id').value;
    const url = id ? `/account/bmi/${id}` : '/account/bmi';
    const method = id ? 'PUT' : 'POST';

    const body = {
        height: parseFloat(document.getElementById('form-height').value),
        weight: parseFloat(document.getElementById('form-weight').value),
        hc: document.getElementById('form-hc').value ? parseFloat(document.getElementById('form-hc').value) : null,
        date: document.getElementById('form-date').value,
    };

    if (isAdmin && !id) {
        const userIdEl = document.getElementById('form-user-id');
        if (userIdEl) body.user_id = parseInt(userIdEl.value);
    }

    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(body),
    })
    .then(r => {
        if (r.status === 422) {
            return r.json().then(data => { throw data; });
        }
        return r.json();
    })
    .then(() => {
        closeModal();
        window.location.reload();
    })
    .catch(err => {
        if (err.errors) {
            const errDiv = document.getElementById('modal-errors');
            errDiv.classList.remove('hidden');
            errDiv.innerHTML = Object.values(err.errors).flat().map(e => `<div>${e}</div>`).join('');
        }
    });
}

function openDeleteModal(id) {
    deleteId = id;
    const modal = document.getElementById('delete-modal');
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
}

function closeDeleteModal() {
    deleteId = null;
    const modal = document.getElementById('delete-modal');
    modal.classList.add('hidden');
    modal.style.display = 'none';
}

function confirmDelete() {
    if (!deleteId) return;

    fetch(`/account/bmi/${deleteId}`, {
        method: 'DELETE',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
    })
    .then(() => {
        closeDeleteModal();
        window.location.reload();
    });
}
</script>
@endsection
