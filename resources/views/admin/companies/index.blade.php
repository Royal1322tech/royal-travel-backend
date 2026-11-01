<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Companies | Royal Travel Admin</title>

    <link rel="stylesheet"
        @vite(['resources/css/app.css', 'resources/css/companies/index.css'])
</head>

<body>

<main class="companies-page">

    <!-- Page Header -->
    <header class="companies-header">

        <div class="companies-header-content">

            <a href="{{ route('admin.dashboard') }}"
               class="companies-breadcrumb">
                Dashboard
            </a>

            <h1>Companies</h1>

            <p class="companies-description">
                Manage B2B agencies and corporate accounts.
            </p>

        </div>

        <a href="{{ route('admin.companies.create') }}"
           class="btn btn-primary">
            <span aria-hidden="true">+</span>
            Add Company
        </a>

    </header>

    <!-- Pending Approval Summary -->
    <section class="companies-summary"
             aria-label="Company approval summary">

        <div class="summary-icon" aria-hidden="true">
            ◷
        </div>

        <div class="summary-content">
            <span class="summary-label">
                Pending Approvals
            </span>

            <strong class="summary-value">
                {{ $pendingCount ?? 0 }}
            </strong>

            <span class="summary-description">
                Companies waiting for admin review
            </span>
        </div>

        <a href="{{ route('admin.companies.index', ['status' => 'pending']) }}"
           class="summary-link">
            View Pending
        </a>

    </section>

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="alert alert-success" role="status">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-error" role="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Companies Table -->
    <section class="companies-panel">

        <div class="panel-heading">

            <div>
                <h2>All Companies</h2>

                <p>
                    Search and manage registered companies
                </p>
            </div>

            <span class="results-count">
                {{ $companies->total() }} Results
            </span>

        </div>

        <!-- Filters -->
        <form method="GET"
              action="{{ route('admin.companies.index') }}"
              class="companies-filters">

            <div class="search-field">

                <label for="companySearch">
                    Search Companies
                </label>

                <input
                    type="search"
                    id="companySearch"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Company name or registration number"
                >

            </div>

            <div class="status-field">

                <label for="companyStatus">
                    Status
                </label>

                <select id="companyStatus" name="status">

                    <option value="">
                        All Statuses
                    </option>

                    <option value="pending"
                        @selected($status === 'pending')>
                        Pending Approval
                    </option>

                    <option value="approved"
                        @selected($status === 'approved')>
                        Approved
                    </option>

                    <option value="suspended"
                        @selected($status === 'suspended')>
                        Suspended
                    </option>

                    <option value="rejected"
                        @selected($status === 'rejected')>
                        Rejected
                    </option>

                </select>

            </div>

            <div class="filter-actions">

                <button type="submit"
                        class="btn btn-primary">
                    Search
                </button>

                <a href="{{ route('admin.companies.index') }}"
                   class="btn btn-outline">
                    Reset
                </a>

            </div>

        </form>

        <!-- Table -->
        <div class="companies-table-wrapper">

            <table class="companies-table">

                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Registration</th>
                        <th>Country</th>
                        <th>Business Type</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($companies as $company)

                        <tr>

                            <td>
                                <div class="company-name">
                                    {{ $company->display_name ?: $company->legal_name }}
                                </div>

                                <div class="company-city">
                                    {{ $company->city ?: '—' }}
                                </div>
                            </td>

                            <td>
                                {{ $company->registration_no }}
                            </td>

                            <td>
                                <span class="country-code">
                                    {{ $company->country_code }}
                                </span>
                            </td>

                            <td>
                                {{ ucwords(str_replace('_', ' ', $company->business_type)) }}
                            </td>

                            <td>
                                <span @class([
                                    'status-badge',
                                    'status-pending' => $company->status === 'pending',
                                    'status-approved' => $company->status === 'approved',
                                    'status-suspended' => $company->status === 'suspended',
                                    'status-rejected' => $company->status === 'rejected',
                                ])>
                                    <span class="status-dot"></span>

                                    {{ $company->status === 'pending'
                                        ? 'Pending Approval'
                                        : ucfirst($company->status) }}
                                </span>
                            </td>

                            <td>
                                {{ $company->created_at?->format('d M Y') ?? '—' }}
                            </td>

                            <td>
                                <div class="company-actions">

                                    <a href="{{ route('admin.companies.show', $company->id) }}"
                                       class="action-link">
                                        View
                                    </a>

                                    <a href="{{ route('admin.companies.edit', $company->id) }}"
                                       class="action-link">
                                        Edit
                                    </a>

                                </div>
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="7" class="empty-state">

                                <div class="empty-icon" aria-hidden="true">
                                    ▤
                                </div>

                                <h3>No companies found</h3>

                                <p>
                                    Try changing the search or status filter.
                                </p>

                                <a href="{{ route('admin.companies.index') }}"
                                   class="btn btn-outline">
                                    Clear Filters
                                </a>

                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        <!-- Pagination -->
        @if($companies->hasPages())

            <div class="companies-pagination">
                {{ $companies->links() }}
            </div>

        @endif

    </section>

</main>

</body>
</html>
