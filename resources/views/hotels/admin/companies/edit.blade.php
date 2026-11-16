
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Company | Royal Travel</title>
    <style>
        :root { --navy:#02143C; --border:#E5EAF1; }
        * { box-sizing:border-box; }
        body { margin:0; background:#F7F9FC; color:#17243B; font-family:Inter,Arial,sans-serif; }
        .container { max-width:850px; margin:45px auto; padding:0 24px; }
        h1 { color:var(--navy); }
        .card { background:white; border:1px solid var(--border); border-radius:16px; padding:32px; }
        .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px; }
        .full { grid-column:1/-1; }
        label { display:block; font-weight:600; font-size:13px; margin-bottom:8px; }
        input,select,textarea { width:100%; padding:12px; border:1px solid #D9E1EA; border-radius:9px; font:inherit; }
        textarea { min-height:100px; }
        .actions { display:flex; justify-content:flex-end; gap:12px; margin-top:28px; }
        .btn { display:inline-block; border:1px solid var(--border); background:white; color:var(--navy); padding:12px 20px; border-radius:9px; text-decoration:none; cursor:pointer; font-weight:600; }
        .primary { background:var(--navy); color:white; }
        .alert { background:#FFF1F0; color:#9F2620; border-radius:10px; padding:15px; margin-bottom:20px; }
        .muted { color:#64748B; font-size:13px; }
        @media(max-width:650px) { .grid { grid-template-columns:1fr; } .full { grid-column:auto; } }
    </style>
</head>
<body>
<div class="container">
    <a class="muted" href="{{ route('admin.companies.show', $company->id) }}">
        Back to Company
    </a>
    <h1>Edit Company</h1>
    <p class="muted">Update company information without changing its registration identity.</p>

    @if($errors->any())
        <div class="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <form method="POST"
              action="{{ route('admin.companies.update', $company->id) }}">
            @csrf
            @method('PUT')

            <div class="grid">
                <div>
                    <label for="legal_name">Legal Company Name *</label>
                    <input id="legal_name" name="legal_name" required maxlength="255"
                           value="{{ old('legal_name', $company->legal_name) }}">
                </div>

                <div>
                    <label for="display_name">Display Name</label>
                    <input id="display_name" name="display_name" maxlength="255"
                           value="{{ old('display_name', $company->display_name) }}">
                </div>

                <div>
                    <label for="business_type">Business Type *</label>
                    <select id="business_type" name="business_type" required>
                        <option value="travel_agency"
                            @selected(old('business_type', $company->business_type) === 'travel_agency')>
                            Travel Agency
                        </option>
                        <option value="corporate"
                            @selected(old('business_type', $company->business_type) === 'corporate')>
                            Corporate
                        </option>
                    </select>
                </div>

                <div>
                    <label for="employee_count">Number of Employees</label>
                    <input id="employee_count" name="employee_count" type="number"
                           min="0" max="1000000"
                           value="{{ old('employee_count', $company->employee_count) }}">
                </div>

                <div>
                    <label>Registration Number</label>
                    <input value="{{ $company->registration_no }}" disabled>
                </div>

                <div>
                    <label>Country Code</label>
                    <input value="{{ $company->country_code }}" disabled>
                </div>

                <div>
                    <label for="city">City *</label>
                    <input id="city" name="city" required maxlength="150"
                           value="{{ old('city', $company->city) }}">
                </div>

                <div class="full">
                    <label for="address">Company Address *</label>
                    <textarea id="address" name="address" required maxlength="2000">{{ old('address', $company->address) }}</textarea>
                </div>
            </div>

            <div class="actions">
                <a class="btn"
                   href="{{ route('admin.companies.show', $company->id) }}">
                    Cancel
                </a>
                <button class="btn primary" type="submit">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
