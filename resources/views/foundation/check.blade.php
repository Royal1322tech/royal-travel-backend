<!DOCTYPE html>
<html lang="{{ $locale ?? app()->getLocale() }}"
      dir="{{ $direction ?? (app()->getLocale() === 'ar' ? 'rtl' : 'ltr') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Royal Travel Platform - Foundation</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8fafc;
            padding: 50px;
            text-align: center;
        }

        .container {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
        }

        h1 {
            color: #02143C;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            {{ app()->getLocale() === 'ar'
                ? 'منصة رويال للسفر'
                : 'Royal Travel Platform' }}
        </h1>

        <p>
            {{ app()->getLocale() === 'ar'
                ? 'تم تشغيل الهيكل الأساسي بنجاح'
                : 'Foundation structure is working successfully' }}
        </p>

        <p>Language: {{ app()->getLocale() }}</p>
        <p>Direction: {{ app()->getLocale() === 'ar' ? 'RTL' : 'LTR' }}</p>
    </div>
</body>
</html>