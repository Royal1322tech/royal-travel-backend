
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add B2B Company | Royal Travel Admin</title>
    <style>
        :root {
            --navy: #02143C;
            --gold: #B7731E;
            --bg: #F7F9FC;
            --border: #E5EAF1;
            --muted: #64748B;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: #17243B;
            font-family: Inter, Arial, sans-serif;
        }

        .container {
            max-width: 1020px;
            margin: 48px auto;
            padding: 0 24px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 30px;
        }

        h1 {
            font-size: 27px;
            margin: 0 0 8px;
            color: var(--navy);
        }

        .subtitle {
            color: var(--muted);
            margin: 0;
            font-size: 14px;
        }

        .back {
            text-decoration: none;
            color: var(--navy);
            font-weight: 600;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .step-indicator {
            padding: 16px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--muted);
            font-size: 13px;
        }

        .step-indicator.active {
            border-color: var(--navy);
            color: var(--navy);
            font-weight: 700;
        }

        .step-indicator.completed {
            border-color: #C8E6D4;
            color: #167347;
        }

        .step-indicator span {
            display: block;
            font-size: 11px;
            margin-bottom: 5px;
        }

        .panel {
            background: white;
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 32px;
        }

        .panel h2 {
            font-size: 20px;
            margin: 0 0 8px;
            color: var(--navy);
        }

        .description {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 28px;
        }

        .form-step { display: none; }
        .form-step.active { display: block; }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .field.full { grid-column: 1 / -1; }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 600;
        }

        input, select, textarea {
            width: 100%;
            border: 1px solid #D9E1EA;
            border-radius: 9px;
            padding: 12px 13px;
            font: inherit;
            font-size: 14px;
            background: white;
            color: #17243B;
        }

        input:focus, select:focus, textarea:focus {
            outline: 2px solid #C9D7F0;
            border-color: var(--navy);
        }

        textarea { min-height: 100px; resize: vertical; }

        .hint {
            color: var(--muted);
            font-size: 12px;
            margin-top: 6px;
        }

        .error {
            color: #B42318;
            font-size: 12px;
            margin-top: 5px;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            background: #FFF1F0;
            color: #9F2620;
        }

        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            gap: 12px;
        }

        button {
            padding: 12px 22px;
            border-radius: 9px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .primary {
            background: var(--navy);
            color: white;
            border: 1px solid var(--navy);
        }

        .secondary {
            background: white;
            color: var(--navy);
            border: 1px solid var(--border);
        }

        .review {
            display: grid;
            gap: 12px;
        }

        .review-item {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 13px 0;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .review-item strong { overflow-wrap: anywhere; text-align: right; }
        .review-item span { color: var(--muted); }

        @media (max-width: 700px) {
            .grid { grid-template-columns: 1fr; }
            .steps { grid-template-columns: repeat(2, 1fr); }
            .panel { padding: 22px; }
            .header { align-items: flex-start; flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Add New B2B Company</h1>
            <p class="subtitle">
                Create an approved company and invite its administrator.
            </p>
        </div>
        <a class="back" href="{{ route('admin.dashboard') }}">Back to Dashboard</a>
    </div>

    <div class="steps" id="steps">
        <div class="step-indicator active"><span>STEP 01</span>Company Information</div>
        <div class="step-indicator"><span>STEP 02</span>Contact Information</div>
        <div class="step-indicator"><span>STEP 03</span>Upload Documents</div>
        <div class="step-indicator"><span>STEP 04</span>Review & Submit</div>
    </div>

    @if ($errors->any())
        <div class="alert" role="alert">
            Please correct the following errors:
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('success'))
        <div class="alert" style="background:#EAF8F0;color:#167347">
            {{ session('success') }}
        </div>
    @endif

    <form id="companyForm"
          method="POST"
          action="{{ route('admin.companies.store') }}"
          enctype="multipart/form-data">
        @csrf

        <div class="panel">
            <section class="form-step active">
                <h2>Company Information</h2>
                <p class="description">Enter the company's legal and business details.</p>

                <div class="grid">
                    <div class="field">
                        <label for="legal_name">Company Name *</label>
                        <input id="legal_name" name="legal_name" required maxlength="255"
                               value="{{ old('legal_name') }}">
                    </div>

                    <div class="field">
                        <label for="business_type">Business Type *</label>
                        <select id="business_type" name="business_type" required>
                            <option value="">Select type</option>
                            <option value="travel_agency" @selected(old('business_type') === 'travel_agency')>Travel Agency</option>
                            <option value="corporate" @selected(old('business_type') === 'corporate')>Corporate</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="employee_count">Number of Employees</label>
                        <input id="employee_count" type="number" min="0" max="1000000"
                               name="employee_count" value="{{ old('employee_count') }}">
                    </div>

                    <div class="field">
                        <label for="country_code">Country Code (ISO 2) *</label>
                        <input id="country_code" name="country_code" required maxlength="2"
                               placeholder="EG" value="{{ old('country_code') }}">
                    </div>

                    <div class="field">
                        <label for="city">City *</label>
                        <input id="city" name="city" required maxlength="150"
                               value="{{ old('city') }}">
                    </div>

                    <div class="field">
                        <label for="registration_no">Commercial Registration Number *</label>
                        <input id="registration_no" name="registration_no" required maxlength="120"
                               value="{{ old('registration_no') }}">
                    </div>

                    <div class="field full">
                        <label for="address">Company Address *</label>
                        <textarea id="address" name="address" required maxlength="2000">{{ old('address') }}</textarea>
                    </div>
                </div>
            </section>

            <section class="form-step">
                <h2>Contact Information</h2>
                <p class="description">The contact will receive a password setup invitation.</p>

                <div class="grid">
                    <div class="field">
                        <label for="contact_name">Full Name *</label>
                        <input id="contact_name" name="contact_name" required maxlength="255"
                               value="{{ old('contact_name') }}">
                    </div>

                    <div class="field">
                        <label for="contact_email">Email Address *</label>
                        <input id="contact_email" type="email" name="contact_email" required
                               value="{{ old('contact_email') }}">
                    </div>

                    <div class="field">
                        <label for="contact_phone">Phone Number *</label>
                        <input id="contact_phone" name="contact_phone" required
                               placeholder="+201000000000" value="{{ old('contact_phone') }}">
                    </div>

                    <div class="field">
                        <label for="contact_nationality">Nationality *</label>
                        <input id="contact_nationality" name="contact_nationality" required
                               value="{{ old('contact_nationality') }}">
                    </div>

                    <div class="field full">
                        <label for="contact_id_number">ID / Passport Number *</label>
                        <input id="contact_id_number" name="contact_id_number" required
                               value="{{ old('contact_id_number') }}">
                    </div>

                    <div class="field full">
                        <label for="contact_id_document">ID Document * (PDF/JPG/PNG, max 5MB)</label>
                        <input id="contact_id_document" type="file" name="contact_id_document"
                               accept=".pdf,.jpg,.jpeg,.png" required>
                    </div>
                </div>
            </section>

            <section class="form-step">
                <h2>Upload Documents</h2>
                <p class="description">Upload company registration and compliance documents.</p>

                <div class="grid">
                    <div class="field full">
                        <label for="commercial_registration">Commercial Registration * (max 10MB)</label>
                        <input id="commercial_registration" type="file" name="commercial_registration"
                               accept=".pdf,.jpg,.jpeg,.png" required>
                    </div>

                    <div class="field full" id="tourismLicenseField">
                        <label for="tourism_license">Tourism License (Travel Agency only)</label>
                        <input id="tourism_license" type="file" name="tourism_license"
                               accept=".pdf,.jpg,.jpeg,.png">
                    </div>

                    <div class="field full">
                        <label for="tax_certificate">Tax Certificate * (max 10MB)</label>
                        <input id="tax_certificate" type="file" name="tax_certificate"
                               accept=".pdf,.jpg,.jpeg,.png" required>
                    </div>
                </div>
            </section>

            <section class="form-step">
                <h2>Review & Submit</h2>
                <p class="description">
                    Review the information before creating the company.
                    The company will be approved immediately.
                </p>
                <div class="review" id="review"></div>
                <p class="hint">
                    The company administrator will receive a password setup email
                    once the invitation service is configured.
                </p>
            </section>

            <div class="actions">
                <button type="button" class="secondary" id="previousBtn" hidden>Previous</button>
                <div style="margin-left:auto;display:flex;gap:12px">
                    <button type="button" class="primary" id="nextBtn">Continue</button>
                    <button type="submit" class="primary" id="submitBtn" hidden >
                        Create Company
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    const form = document.getElementById('companyForm');
    const sections = [...document.querySelectorAll('.form-step')];
    const indicators = [...document.querySelectorAll('.step-indicator')];
    const previousBtn = document.getElementById('previousBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    const businessType = document.getElementById('business_type');
    const tourismField = document.getElementById('tourismLicenseField');
    const tourismInput = document.getElementById('tourism_license');
    let currentStep = 0;

    function updateBusinessType() {
        const agency = businessType.value === 'travel_agency';
        tourismField.hidden = !agency;
        tourismInput.required = agency;
        tourismInput.disabled = !agency;
    }

    function showStep(index) {
        currentStep = index;

        sections.forEach((section, i) => {
            section.classList.toggle('active', i === index);
        });

        indicators.forEach((indicator, i) => {
            indicator.classList.toggle('active', i === index);
            indicator.classList.toggle('completed', i < index);
        });

        previousBtn.hidden = index === 0;
        nextBtn.hidden = index === sections.length - 1;
        submitBtn.hidden = index !== sections.length - 1;

        if (index === sections.length - 1) {
            buildReview();
        }
    }

    function validateStep() {
        const fields = sections[currentStep].querySelectorAll(
            'input:not(:disabled), select:not(:disabled), textarea:not(:disabled)'
        );

        for (const field of fields) {
            if (!field.reportValidity()) {
                field.focus();
                return false;
            }
        }

        return true;
    }

    function buildReview() {
        const review = document.getElementById('review');
        review.replaceChildren();

        const fields = [
            ['Company Name', 'legal_name'],
            ['Business Type', 'business_type'],
            ['Employees', 'employee_count'],
            ['Country', 'country_code'],
            ['City', 'city'],
            ['Registration Number', 'registration_no'],
            ['Address', 'address'],
            ['Contact Name', 'contact_name'],
            ['Contact Email', 'contact_email'],
            ['Contact Phone', 'contact_phone'],
            ['Nationality', 'contact_nationality'],
            ['ID Number', 'contact_id_number'],
        ];

        for (const [label, id] of fields) {
            const element = document.getElementById(id);
            const row = document.createElement('div');
            row.className = 'review-item';

            const name = document.createElement('span');
            name.textContent = label;

            const value = document.createElement('strong');
            value.textContent = id === 'contact_id_number' && element.value ? '••••' + element.value.slice(-4) : (element.value || '—');

            row.append(name, value);
            review.appendChild(row);
        }

        for (const id of [
            'contact_id_document',
            'commercial_registration',
            'tourism_license',
            'tax_certificate'
        ]) {
            const input = document.getElementById(id);

            if (!input.disabled && input.files.length) {
                const row = document.createElement('div');
                row.className = 'review-item';

                const name = document.createElement('span');
                name.textContent = id.replaceAll('_', ' ');

                const value = document.createElement('strong');
                value.textContent = input.files[0].name;

                row.append(name, value);
                review.appendChild(row);
            }
        }
    }

    businessType.addEventListener('change', updateBusinessType);

    nextBtn.addEventListener('click', () => {
        if (validateStep()) {
            showStep(currentStep + 1);
        }
    });

    previousBtn.addEventListener('click', () => {
        showStep(currentStep - 1);
    });

    form.addEventListener('submit', event => {
        if (submitBtn.disabled) {
            event.preventDefault();
        }
    });

    updateBusinessType();
    showStep(0);
</script>
</body>
</html>
