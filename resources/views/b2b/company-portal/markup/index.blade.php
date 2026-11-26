@extends('b2b.company-portal.layout')

@section('title', 'Markup Management')

@section('content')

<style>
    .markup-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
        margin-bottom: 24px;
    }

    .markup-layout {
        display: grid;
        grid-template-columns: 1.3fr 1fr;
        gap: 24px;
        align-items: start;
    }

    .markup-card {
        background: #fff;
        border: 1px solid #dce3ed;
        border-radius: 14px;
        padding: 25px;
    }

    .markup-label {
        color: #64748b;
        font-size: 14px;
    }

    .markup-value {
        font-size: 30px;
        font-weight: 700;
        color: #10254b;
        margin-top: 12px;
    }

    .markup-field {
        margin-bottom: 22px;
    }

    .markup-field label {
        display: block;
        font-weight: 600;
        margin-bottom: 9px;
    }

    .markup-field input {
        width: 100%;
        padding: 13px;
        border: 1px solid #d6deea;
        border-radius: 8px;
        box-sizing: border-box;
        font-size: 15px;
        background: #fff;
    }

    .markup-help {
        color: #64748b;
        font-size: 13px;
        margin-top: 8px;
        line-height: 1.6;
    }

    .markup-button {
        background: #10254b;
        color: #fff;
        padding: 13px 24px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
    }

    .markup-button:disabled {
        opacity: .45;
        cursor: not-allowed;
    }

    .preview-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        padding: 17px 0;
        border-bottom: 1px solid #edf0f5;
    }

    .preview-total {
        background: #10254b;
        color: white;
        padding: 22px;
        border-radius: 10px;
        margin-top: 20px;
    }

    .markup-alert {
        padding: 15px 18px;
        border-radius: 9px;
        margin-bottom: 20px;
        line-height: 1.6;
    }

    @media(max-width: 900px) {
        .markup-grid,
        .markup-layout {
            grid-template-columns: 1fr;
        }
    }
</style>

<h1>Markup Management</h1>

<p style="color:#64748b;margin-bottom:25px">
    Manage your company's hotel selling prices.
</p>

@if(session('success'))
    <div class="markup-alert"
         style="background:#e7f5ec;color:#18794e">

        {{ session('success') }}

    </div>
@endif

@if(session('pricing_conflict'))
    <div class="markup-alert"
         style="background:#fff4df;color:#92400e">

        {{ session('pricing_conflict') }}

    </div>
@endif

@if($errors->any())
    <div class="markup-alert"
         style="background:#fef2f2;color:#b91c1c">

        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach

    </div>
@endif

@unless($hasRoyalPrice)
    <div class="markup-alert"
         style="background:#fff4df;color:#92400e">

        <strong>Royal Travel pricing is not configured.</strong>

        <div>
            Please contact Royal Travel administration
            to configure your company purchase pricing
            before setting your markup.
        </div>

    </div>
@endunless

<div class="markup-grid">

    <div class="markup-card">
        <div class="markup-label">
            Current Percentage
        </div>

        <div class="markup-value">
            {{ rtrim(rtrim(number_format((float) ($companyRule['percentage'] ?? 0), 6, '.', ''), '0'), '.') }}%
        </div>
    </div>

    <div class="markup-card">
        <div class="markup-label">
            Current Fixed Markup
        </div>

        <div class="markup-value">
            ${{ number_format((float) ($companyRule['fixed'] ?? 0), 2) }}
        </div>
    </div>

    <div class="markup-card">
        <div class="markup-label">
            Pricing Version
        </div>

        <div class="markup-value">
            {{ $companyRule['version'] ?? 0 }}
        </div>
    </div>

</div>

<div class="markup-layout">

    <section class="markup-card">

        <h2 style="margin-top:0">
            Company Pricing Settings
        </h2>

        <p class="markup-help">
            Configure the additional markup applied
            to your company's hotel purchase price.
        </p>

        <form method="POST"
              action="{{ route('company-portal.markup.save') }}">

            @csrf

            <input type="hidden"
                   name="expected_version"
                   value="{{ $companyRule['version'] ?? 0 }}">

            <div class="markup-field">

                <label for="percentage">
                    Percentage Markup (%)
                </label>

                <input type="number"
                       id="percentage"
                       name="percentage"
                       min="0"
                       max="100"
                       step="0.000001"
                       value="{{ old('percentage', $companyRule['percentage'] ?? '0') }}"
                       required>

                <div class="markup-help">
                    Percentage added to your company
                    purchase price.
                </div>

            </div>

            <div class="markup-field">

                <label for="fixed">
                    Fixed Markup (USD)
                </label>

                <input type="number"
                       id="fixed"
                       name="fixed"
                       min="0"
                       max="1000000"
                       step="0.01"
                       value="{{ old('fixed', $companyRule['fixed'] ?? '0') }}"
                       required>

                <div class="markup-help">
                    Additional fixed amount applied
                    to the hotel price.
                </div>

            </div>

            <button type="submit"
                    class="markup-button"
                    @disabled(!$hasRoyalPrice)>

                Save Markup Settings

            </button>

        </form>

    </section>

    <aside class="markup-card">

        <h2 style="margin-top:0">
            Price Preview
        </h2>

        <p class="markup-help">
            Illustrative calculation based on a sample
            company purchase price of 100 USD.
        </p>

        <div class="preview-row">
            <span>Company Purchase Price</span>
            <strong>$100.00</strong>
        </div>

        <div class="preview-row">
            <span>Percentage Markup</span>
            <strong id="preview-percentage">$0.00</strong>
        </div>

        <div class="preview-row">
            <span>Fixed Markup</span>
            <strong id="preview-fixed">$0.00</strong>
        </div>

        <div class="preview-row">
            <span>Total Company Markup</span>
            <strong id="preview-markup">$0.00</strong>
        </div>

        <div class="preview-total">

            <div style="font-size:13px;opacity:.8">
                Final Website Selling Price
            </div>

            <div id="preview-total"
                 style="font-size:32px;font-weight:700;margin-top:10px">

                $100.00

            </div>

        </div>

        <p class="markup-help">
            This preview is for illustration only.
            Actual prices are calculated by the server.
        </p>

    </aside>

</div>

<script>
(() => {

    const percentage = document.getElementById('percentage');
    const fixed = document.getElementById('fixed');

    const base = 100;

    const money = value => '$' + value.toFixed(2);

    function updatePreview() {

        const p = Number(percentage.value);
        const f = Number(fixed.value);

        if (
            percentage.value === '' ||
            fixed.value === '' ||
            !Number.isFinite(p) ||
            !Number.isFinite(f) ||
            p < 0 ||
            p > 100 ||
            f < 0 ||
            f > 1000000
        ) {
            [
                'preview-percentage',
                'preview-fixed',
                'preview-markup',
                'preview-total'
            ].forEach(id => {
                document.getElementById(id).textContent = '—';
            });

            return;
        }

        const percentageAmount = base * p / 100;

        const totalMarkup =
            Math.round((percentageAmount + f) * 100) / 100;

        const total = base + totalMarkup;

        document.getElementById(
            'preview-percentage'
        ).textContent = money(percentageAmount);

        document.getElementById(
            'preview-fixed'
        ).textContent = money(f);

        document.getElementById(
            'preview-markup'
        ).textContent = money(totalMarkup);

        document.getElementById(
            'preview-total'
        ).textContent = money(total);

    }

    percentage.addEventListener('input', updatePreview);
    fixed.addEventListener('input', updatePreview);

    updatePreview();

})();
</script>

@endsection