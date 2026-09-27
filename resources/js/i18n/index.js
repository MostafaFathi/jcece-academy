import { createI18n } from 'vue-i18n';

export const messages = {
    ar: {
        brand: { name: 'أكاديمية الجزيرة', tagline: 'نتعلّم اليوم... لنَبني مستقبلًا أفضل', promise: 'نصنع تجربة تعليمية مهنية تفتح أبواب المستقبل.', description: 'منصة تعليم وتدريب مهني تجمع المحتوى والتقييم والتجارة الإلكترونية في تجربة واضحة وآمنة.' },
        common: { login: 'تسجيل الدخول', logout: 'تسجيل الخروج', dashboard: 'لوحة التحكم', home: 'الرئيسية', language: 'English', menu: 'فتح القائمة', close: 'إغلاق', upcoming: 'قريبًا', backHome: 'العودة للرئيسية', loading: 'جارٍ التحميل…', retry: 'إعادة المحاولة' },
        auth: { title: 'مرحبًا بعودتك', subtitle: 'سجّل الدخول للمتابعة إلى منصتك التعليمية.', email: 'البريد الإلكتروني', password: 'كلمة المرور', remember: 'تذكرني', invalid: 'تعذر تسجيل الدخول. راجع البيانات وحاول مجددًا.' },
        nav: { overview: 'نظرة عامة', courses: 'الدورات', learning: 'تعلّمي', content: 'إدارة المحتوى', users: 'المستخدمون', assignments: 'التكليفات', tickets: 'تذاكر الدعم', orders: 'الطلبات' },
        dashboard: { greeting: 'أهلًا، {name}', subtitle: 'هذه نقطة البداية الآمنة لتجربة JCEC Academy.', account: 'حسابك', roles: 'الأدوار', permissions: 'الصلاحيات', next: 'الوحدات القادمة ستظهر هنا بعد تنفيذها.' },
        errors: { forbiddenTitle: 'لا تملك صلاحية الوصول', forbiddenText: 'هذه الصفحة تتطلب صلاحيات إضافية.', notFoundTitle: 'الصفحة غير موجودة', notFoundText: 'قد يكون الرابط غير صحيح أو تم نقل الصفحة.', startupTitle: 'تعذر تشغيل التطبيق', startupText: 'لم نتمكن من التحقق من الجلسة. حاول مجددًا.' },
        pages: { student: 'مساحة المتدرب', admin: 'لوحة الإدارة', instructor: 'مساحة المدرب', support: 'المبيعات والدعم' },
    },
    en: {
        brand: { name: 'JCEC Academy', tagline: 'Learn today… build a better tomorrow', promise: 'A professional learning experience that opens doors to the future.', description: 'A professional learning and training platform bringing content, assessment, and commerce into one clear and secure experience.' },
        common: { login: 'Sign in', logout: 'Sign out', dashboard: 'Dashboard', home: 'Home', language: 'العربية', menu: 'Open menu', close: 'Close', upcoming: 'Coming soon', backHome: 'Back home', loading: 'Loading…', retry: 'Try again' },
        auth: { title: 'Welcome back', subtitle: 'Sign in to continue to your learning platform.', email: 'Email address', password: 'Password', remember: 'Remember me', invalid: 'Unable to sign in. Check your details and try again.' },
        nav: { overview: 'Overview', courses: 'Courses', learning: 'My learning', content: 'Content management', users: 'Users', assignments: 'Assignments', tickets: 'Support tickets', orders: 'Orders' },
        dashboard: { greeting: 'Welcome, {name}', subtitle: 'This is the secure starting point for JCEC Academy.', account: 'Your account', roles: 'Roles', permissions: 'Permissions', next: 'Upcoming modules will appear here as they are implemented.' },
        errors: { forbiddenTitle: 'Access denied', forbiddenText: 'This page requires additional permissions.', notFoundTitle: 'Page not found', notFoundText: 'The address may be incorrect or the page has moved.', startupTitle: 'Unable to start the app', startupText: 'We could not verify your session. Please try again.' },
        pages: { student: 'Student area', admin: 'Administration', instructor: 'Instructor area', support: 'Sales & support' },
    },
};

const savedLocale = localStorage.getItem('jcec.locale');
const locale = savedLocale === 'en' ? 'en' : 'ar';

export function applyDocumentLocale(selectedLocale) {
    document.documentElement.lang = selectedLocale;
    document.documentElement.dir = selectedLocale === 'ar' ? 'rtl' : 'ltr';
}

applyDocumentLocale(locale);

const i18n = createI18n({ legacy: false, locale, fallbackLocale: 'ar', messages });

export function setLocale(selectedLocale) {
    const normalizedLocale = selectedLocale === 'en' ? 'en' : 'ar';
    i18n.global.locale.value = normalizedLocale;
    localStorage.setItem('jcec.locale', normalizedLocale);
    applyDocumentLocale(normalizedLocale);
}

export default i18n;
