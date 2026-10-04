<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>دسترسی غیرمجاز - {{ config('app.name') }}</title>
    <style>
        @font-face {
            font-family: 'Vazirmatn';
            src: url('{{ asset("fonts/Vazirmatn-Regular.woff2") }}') format('woff2');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        * { box-sizing: border-box; }

        body {
            display: grid;
            min-height: 100vh;
            margin: 0;
            place-items: center;
            padding: 1.5rem;
            color: #0f172a;
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            background:
                radial-gradient(circle at 15% 15%, rgba(45, 212, 191, .32), transparent 26rem),
                radial-gradient(circle at 88% 22%, rgba(56, 189, 248, .25), transparent 28rem),
                radial-gradient(circle at 60% 92%, rgba(167, 139, 250, .22), transparent 30rem),
                linear-gradient(145deg, #ecfeff, #f8fafc 50%, #f0fdf4);
        }

        main {
            width: min(100%, 34rem);
            padding: clamp(1.7rem, 5vw, 3rem);
            text-align: center;
            border: 1px solid rgba(255, 255, 255, .82);
            border-radius: 2rem;
            background: rgba(255, 255, 255, .68);
            box-shadow: 0 24px 64px rgba(15, 23, 42, .13);
            -webkit-backdrop-filter: blur(24px) saturate(145%);
            backdrop-filter: blur(24px) saturate(145%);
        }

        .mark {
            display: grid;
            width: 4rem;
            height: 4rem;
            margin: 0 auto 1rem;
            place-items: center;
            color: #fff;
            font-size: 1.7rem;
            font-weight: 900;
            border-radius: 1.35rem;
            background: linear-gradient(135deg, #059669, #0d9488);
            box-shadow: 0 14px 30px rgba(5, 150, 105, .27);
        }

        .code { color: #059669; font-size: .9rem; font-weight: 800; }
        h1 { margin: .4rem 0 .8rem; font-size: clamp(1.65rem, 5vw, 2.25rem); }
        p { margin: 0; color: #475569; line-height: 2; }
        .hint { margin-top: 1rem; padding: .85rem 1rem; border-radius: 1rem; background: rgba(255, 255, 255, .56); }

        a {
            display: inline-flex;
            margin-top: 1.4rem;
            padding: .8rem 1.35rem;
            color: #fff;
            text-decoration: none;
            border-radius: 1rem;
            background: linear-gradient(135deg, #059669, #0d9488);
            box-shadow: 0 10px 24px rgba(5, 150, 105, .22);
        }

        footer { margin-top: 1.5rem; color: #64748b; font-size: .78rem; }
    </style>
</head>
<body>
<main>
    <div class="mark">م</div>
    <div class="code">خطای ۴۰۳</div>
    <h1>دسترسی غیرمجاز است</h1>
    <p>حساب شما اجازه ورود به این پنل را ندارد.</p>
    <p class="hint">فعال‌بودن حساب و آژانس را بررسی کنید و مطمئن شوید از نشانی پنل مربوط به نقش خود وارد شده‌اید.</p>
    <a href="{{ request()->url() }}">تلاش دوباره</a>
    <footer>{{ config('app.name') }} · طراحی‌شده توسط فندوق استودیو</footer>
</main>
</body>
</html>
