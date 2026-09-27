<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#6B1D32">
        <meta name="description" content="JCEC Academy learning and commerce platform">
        <title>{{ config('app.name', 'JCEC Academy') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <noscript>يتطلب هذا التطبيق تفعيل JavaScript.</noscript>
        <div id="app">
            <div style="min-height: 100vh; display: grid; place-items: center; background: #f8fafc; color: #6B1D32; font-family: Tahoma, Arial, sans-serif;">
                <div style="text-align: center;">
                    <img src="/assets/images/logo-1.png" alt="JCEC Academy" width="96" height="96" style="margin: 0 auto 16px; object-fit: contain;">
                    <strong>جارٍ تحميل JCEC Academy…</strong>
                </div>
            </div>
        </div>
    </body>
</html>
