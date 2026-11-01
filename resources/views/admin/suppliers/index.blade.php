@extends('admin.layouts.app')

@section('title', 'Suppliers Management')

@section('styles')
    @vite('resources/css/suppliers/index.css')
@endsection

@section('content')
<div class="suppliers-page">

    <div class="suppliers-heading">
        <div>
            <p class="suppliers-eyebrow">MANAGEMENT</p>
            <h1>Suppliers</h1>
            <p>Manage hotel suppliers and their integration status.</p>
        </div>
    </div>

    <div class="supplier-stats">
        <div class="supplier-stat">
            <span>Total Suppliers</span>
            <strong>{{ $stats['total'] }}</strong>
        </div>

        <div class="supplier-stat">
            <span>Pending</span>
            <strong>{{ $stats['pending'] }}</strong>
        </div>

        <div class="supplier-stat">
            <span>Active</span>
            <strong>{{ $stats['active'] }}</strong>
        </div>

        <div class="supplier-stat">
            <span>Inactive</span>
            <strong>{{ $stats['inactive'] }}</strong>
        </div>
    </div>

    <div class="suppliers-panel">
        <div class="suppliers-panel-header">
            <div>
                <h2>Supplier Directory</h2>
                <p>All connected and pending hotel suppliers.</p>
            </div>
        </div>

        <form method="GET"
              action="{{ route('admin.suppliers.index') }}"
              class="supplier-filters">

            <input
                type="search"
                name="search"
                value="{{ $search }}"
                placeholder="Search supplier, code or adapter..."
            >

            <select name="status">
                <option value="">All statuses</option>
                <option value="pending" @selected($status === 'pending')>
                    Pending
                </option>
                <option value="active" @selected($status === 'active')>
                    Active
                </option>
                <option value="inactive" @selected($status === 'inactive')>
                    Inactive
                </option>
            </select>

            <button type="submit">Apply Filters</button>

            <a href="{{ route('admin.suppliers.index') }}">
                Reset
            </a>
        </form>

        <div class="supplier-table-wrap">
            <table class="supplier-table">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>Code</th>
                        <th>Adapter</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td>
                                <div class="supplier-name-cell">
                                    <span class="supplier-avatar">
                                        {{ strtoupper(substr($supplier->name, 0, 1)) }}
                                    </span>
                                    <strong>{{ $supplier->name }}</strong>
                                </div>
                            </td>

                            <td>{{ $supplier->code }}</td>

                            <td>{{ $supplier->adapter_code ?? '—' }}</td>

                            <td>
                                <span class="supplier-status supplier-status--{{ in_array($supplier->status, ['pending', 'active', 'inactive'], true) ? $supplier->status : 'other' }}">
                                    {{ ucfirst($supplier->status) }}
                                </span>
                            </td>

                            <td>
                                {{ \Illuminate\Support\Carbon::parse($supplier->created_at)->format('d M Y') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="supplier-empty">
                                No suppliers found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="supplier-pagination">
            {{ $suppliers->links() }}
        </div>
    </div>

</div>
@endsection
