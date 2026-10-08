import { ar as commerce } from './commerce';
import { ar as learning } from './learning';
import { ar as assessments } from './assessments';
import { ar as reviewsSupport } from './reviews-support';
import { ar as admin } from './admin';
import { ar as adminContent } from './admin-content';
import { ar as operations } from './operations';
import { ar as instructor } from './instructor';
import { ar as certificatePolicy } from './certificate-policy';
import { ar as path } from './sequential';
import { ar as audit } from './audit';
import { ar as phase15f } from './phase15f';
import { ar as reports } from './reports';
import { ar as rolePermissions } from './role-permissions';
import { ar as assessmentAuthoring } from './assessment-authoring';

export default {
    commerce,
    learning,
    assessments: { ...assessments, reasons: { ...assessments.reasons, certificate_configuration_missing: 'لم تُضبط سياسة الشهادة لهذه الدورة.', final_exam_unavailable: 'الاختبار النهائي غير متاح.', final_exam_incomplete: 'أكمل الاختبار النهائي أولًا.', final_exam_failed: 'لم تحقق علامة اجتياز الاختبار النهائي.', required_assignments_incomplete: 'أكمل الواجبات المطلوبة واجتز تقييمها.', admin_approval_pending: 'بانتظار موافقة الإدارة على الشهادة.' } },
    ...reviewsSupport,
    admin,
    curriculum: adminContent.curriculum,
    operations,
    instructor,
    certificatePolicy,
    path,
    audit,
    reports,
    rolePermissions,
    assessmentAuthoring,
    ...phase15f,
    brand: {
        name: 'أكاديمية الجزيرة',
        tagline: 'نتعلّم اليوم... لنَبني مستقبلًا أفضل',
        promise: 'تعلّم مهارات مهنية تصنع فرقًا حقيقيًا في مستقبلك.',
        description: 'منصة عربية للتعليم والتدريب المهني تجمع المحتوى المتخصص والتقييم والتجربة العملية في مكان واحد.',
    },
    common: {
        login: 'تسجيل الدخول', logout: 'تسجيل الخروج', dashboard: 'لوحة التحكم', home: 'الرئيسية', language: 'English', menu: 'فتح القائمة', close: 'إغلاق', upcoming: 'قريبًا', backHome: 'العودة للرئيسية', loading: 'جارٍ التحميل…', retry: 'إعادة المحاولة', viewAll: 'عرض الكل', explore: 'استكشف الآن', learnMore: 'اعرف المزيد', previous: 'السابق', next: 'التالي', page: 'صفحة {current} من {total}', search: 'بحث', reset: 'إعادة ضبط', apply: 'تطبيق', notSpecified: 'غير محدد', skipContent: 'الانتقال إلى المحتوى', back: 'رجوع', email: 'البريد الإلكتروني', yes: 'نعم', no: 'لا', open: 'فتح', unavailable: 'غير متاح حاليًا', results: '{count} نتيجة', noResults: 'لا توجد نتائج', optional: 'اختياري', menuClose: 'إغلاق القائمة', accountMenu: 'قائمة الحساب', breadcrumbs: 'مسار التنقل', status: 'الحالة', free: 'مجاني', price: 'السعر', duration: 'المدة', access: 'مدة الوصول', days: '{count} يوم', minutes: '{count} دقيقة', hours: '{count} ساعة', lifetime: 'وصول دائم', course: 'دورة', courses: 'دورات', package: 'باقة', details: 'التفاصيل', viewDetails: 'عرض التفاصيل', comingSoon: 'ستتوفر هذه الخطوة في المرحلة المخصصة للدفع.',
    },
    auth: {
        title: 'مرحبًا بعودتك', subtitle: 'ادخل إلى مساحتك التعليمية وتابع رحلتك من حيث توقفت.', email: 'البريد الإلكتروني', password: 'كلمة المرور', remember: 'تذكرني', invalid: 'تعذر تسجيل الدخول. راجع البيانات وحاول مجددًا.', submit: 'الدخول إلى حسابي', secure: 'دخول آمن عبر جلسة محمية', emailRequired: 'البريد الإلكتروني مطلوب.', emailInvalid: 'أدخل بريدًا إلكترونيًا صحيحًا.', passwordRequired: 'كلمة المرور مطلوبة.', registrationUnavailable: 'إنشاء الحسابات غير متاح إلكترونيًا حاليًا. تواصل مع الأكاديمية للانضمام.', noReset: 'استعادة كلمة المرور غير متاحة من خلال المنصة حاليًا.',
        registerTitle: 'أنشئ حساب متدرب', registerSubtitle: 'ابدأ رحلتك التعليمية بحسابك الشخصي.', registerLink: 'إنشاء حساب جديد', name: 'الاسم الكامل', confirmPassword: 'تأكيد كلمة المرور', registerSubmit: 'إنشاء الحساب', registerSuccess: 'تم إنشاء حسابك. يمكنك تسجيل الدخول الآن.', alreadyHaveAccount: 'لديك حساب؟ تسجيل الدخول', forgotLink: 'نسيت كلمة المرور؟', forgotTitle: 'استعادة كلمة المرور', forgotSubtitle: 'أدخل بريدك الإلكتروني وسنرسل رابط الاستعادة إذا كان الحساب موجودًا.', forgotSubmit: 'إرسال رابط الاستعادة', forgotSuccess: 'إذا كان الحساب موجودًا، سيُرسل رابط الاستعادة إلى بريدك الإلكتروني.', resetTitle: 'تعيين كلمة مرور جديدة', resetSubtitle: 'اختر كلمة مرور جديدة لحسابك.', resetSubmit: 'تغيير كلمة المرور', resetSuccess: 'تم تغيير كلمة المرور. سجّل الدخول بكلمتك الجديدة.', resetInvalid: 'الرابط غير صالح أو منتهي الصلاحية. اطلب رابطًا جديدًا.', requestAgain: 'طلب رابط جديد', nameRequired: 'الاسم مطلوب.', confirmRequired: 'تأكيد كلمة المرور مطلوب.', passwordMismatch: 'كلمتا المرور غير متطابقتين.',
    },
    nav: {
        primary: 'التنقل الرئيسي', mobile: 'التنقل عبر الهاتف', overview: 'نظرة عامة', courses: 'الدورات', packages: 'الباقات والمسارات', howItWorks: 'كيف نتعلّم', learning: 'تعلّمي', content: 'إدارة المحتوى', website: 'الموقع والسياسات', users: 'المستخدمون', assignments: 'التكليفات', tickets: 'تذاكر الدعم', orders: 'الطلبات', main: 'القائمة الرئيسية',
    },
    home: {
        eyebrow: 'مهارات اليوم لمستقبل أكثر إشراقًا', heroTitle: 'تعليم مهني مصمم ليقودك من المعرفة إلى الإنجاز', heroDescription: 'اكتشف دورات ومسارات تعليمية متخصصة، بمنهج واضح وتجربة تتكيف مع طموحك المهني.', browseCourses: 'استكشف الدورات', browsePackages: 'استكشف المسارات', recentEyebrow: 'تعلّم بتركيز', recentTitle: 'أحدث الدورات المتاحة', recentDescription: 'محتوى منشور فعليًا من الأكاديمية، مرتب من الأحدث لتبدأ بخيار يناسب هدفك.', categoriesEyebrow: 'اختر مجالك', categoriesTitle: 'مجالات تعليمية تقرّبك من هدفك', categoriesDescription: 'تصفح التصنيفات المتاحة وانتقل مباشرة إلى الدورات المنشورة في المجال.', packagesEyebrow: 'رحلة متكاملة', packagesTitle: 'باقات ومسارات تجمع لك المعرفة', packagesDescription: 'مجموعة دورات مترابطة ضمن تجربة واحدة واضحة.', howEyebrow: 'خطوات بسيطة', howTitle: 'رحلتك التعليمية تبدأ بقرار', howDescription: 'نوفّر لك مسارًا واضحًا من الاستكشاف وحتى التطبيق العملي.', stepExplore: 'استكشف ما يناسبك', stepExploreText: 'قارن الدورات والباقات المنشورة وفق هدفك ومستواك.', stepLearn: 'تعلّم بوتيرتك', stepLearnText: 'تابع منهجًا منظمًا ومعلومات واضحة عن محتوى كل دورة.', stepGrow: 'ابنِ مهارتك', stepGrowText: 'حوّل المعرفة إلى تقدم مهني بخطوات عملية قابلة للقياس.', whyEyebrow: 'لماذا JCEC', whyTitle: 'تجربة تركّز على ما تحتاجه فعلًا', whyPractical: 'تركيز مهني', whyPracticalText: 'برامج مصممة حول مهارات قابلة للتطبيق في بيئة العمل.', whyClear: 'تجربة واضحة', whyClearText: 'معلومات شفافة عن المنهج والمدة والمستوى قبل اتخاذ قرارك.', whyFlexible: 'تعلم مرن', whyFlexibleText: 'الوصول إلى المحتوى وفق مدة كل دورة أو باقة كما تحددها الأكاديمية.', ctaTitle: 'جاهز لتبدأ خطوتك التالية؟', ctaText: 'تصفح المحتوى المنشور واختر التجربة الأقرب إلى طموحك.', emptyCourses: 'لا توجد دورات منشورة حاليًا.', emptyPackages: 'لا توجد باقات منشورة حاليًا.', emptyCategories: 'لا توجد تصنيفات متاحة حاليًا.',
    },
    catalog: {
        coursesTitle: 'دورات تصنع تقدمًا حقيقيًا', coursesDescription: 'ابحث وصفِّ النتائج باستخدام الخيارات المدعومة فعليًا من الأكاديمية.', packagesTitle: 'باقات ومسارات تعليمية', packagesDescription: 'اكتشف مجموعات الدورات المنشورة واختر الرحلة الأنسب لهدفك.', searchCourses: 'ابحث في اسم الدورة أو وصفها', searchPackages: 'ابحث في اسم الباقة أو وصفها', category: 'التصنيف', allCategories: 'كل التصنيفات', level: 'المستوى', allLevels: 'كل المستويات', language: 'لغة المحتوى', allLanguages: 'كل اللغات', type: 'نوع الباقة', allTypes: 'كل الأنواع', sort: 'الترتيب', latest: 'الأحدث', oldest: 'الأقدم', priceAsc: 'السعر: من الأقل', priceDesc: 'السعر: من الأعلى', titleSort: 'الاسم أبجديًا', price: 'السعر', packagePrice: 'سعر الباقة', free: 'مجاني', featured: 'مختارة', minutes: '{count} دقيقة', courseCount: '{count} دورة', ratingLabel: 'التقييم {rating} من 5 بناءً على {count} مراجعة', filters: 'تصفية النتائج', filtersDescription: 'تُحفظ خياراتك في الرابط لتتمكن من مشاركته.', emptyCourses: 'لم نجد دورات مطابقة', emptyCoursesText: 'جرّب تغيير كلمات البحث أو إزالة بعض عوامل التصفية.', emptyPackages: 'لم نجد باقات مطابقة', emptyPackagesText: 'جرّب تغيير البحث أو نوع الباقة.', loadError: 'تعذر تحميل المحتوى الآن.', resultSummary: 'عرض {from}–{to} من {total} نتيجة',
    },
    course: {
        overview: 'نظرة عامة', curriculum: 'محتوى الدورة', outcomes: 'ماذا ستتعلم', requirements: 'متطلبات الدورة', audience: 'لمن هذه الدورة', tools: 'الأدوات المطلوبة', instructor: 'مدرب الدورة', reviews: 'آراء المتعلمين', reviewCount: '{count} مراجعة منشورة', noReviews: 'لا توجد مراجعات منشورة بعد.', lessons: '{count} درس', preview: 'معاينة متاحة', lesson: 'درس', certificate: 'شهادة إتمام', discussions: 'نقاشات الدورة', accessDays: 'وصول لمدة {count} يوم', accessUnspecified: 'مدة الوصول غير محددة', signInCta: 'سجّل الدخول للمتابعة', purchaseSoon: 'الشراء سيتوفر قريبًا', published: 'دورة منشورة', detailError: 'تعذر تحميل تفاصيل الدورة.', notFound: 'لم يتم العثور على الدورة المطلوبة.', backToCourses: 'العودة إلى الدورات', ratingDistribution: 'توزيع التقييمات',
    },
    packages: {
        ...adminContent.packages,
        included: 'الدورات المشمولة', overview: 'عن الباقة', required: 'أساسية', optional: 'اختيارية', sequential: 'مسار متسلسل', flexible: 'باقة مرنة', lifetime: 'وصول دائم للباقة', accessDays: 'وصول للباقة لمدة {count} يوم', signInCta: 'سجّل الدخول للمتابعة', purchaseSoon: 'شراء الباقة سيتوفر قريبًا', detailError: 'تعذر تحميل تفاصيل الباقة.', notFound: 'لم يتم العثور على الباقة المطلوبة.', backToPackages: 'العودة إلى الباقات', noCourses: 'لا توجد دورات منشورة ضمن هذه الباقة حاليًا.', courseAccessNote: 'مدة الوصول المعروضة تخص الباقة؛ قد تختلف تفاصيل الوصول الفردية للدورات.',
    },
    labels: {
        levels: { beginner: 'مبتدئ', intermediate: 'متوسط', advanced: 'متقدم', all_levels: 'جميع المستويات' },
        languages: { ar: 'العربية', en: 'الإنجليزية' },
        packageTypes: { package: 'باقة دورات', learning_path: 'مسار تعليمي' },
        lessonTypes: { video: 'فيديو', text: 'محتوى نصي', file: 'ملف', link: 'رابط' },
        roles: { student: 'متدرب', instructor: 'مدرب', admin: 'مدير النظام', content_manager: 'مدير محتوى', sales_support: 'المبيعات والدعم' },
        statuses: { active: 'نشط', inactive: 'غير نشط', blocked: 'محظور' },
    },
    footer: { explore: 'استكشف المنصة', learning: 'تعلم بثقة', learningText: 'نوضح لك المحتوى والمستوى والمدة قبل أن تبدأ، لتختار على أساس واضح.', rights: 'جميع الحقوق محفوظة.' },
    policies: { privacy: 'سياسة الخصوصية', terms: 'شروط الاستخدام', refund: 'سياسة الاسترجاع', description: 'الوثائق والسياسات الرسمية للأكاديمية.', notPublished: 'لم تُنشر الصيغة المعتمدة لهذه السياسة بعد. لا يُعد هذا النص سياسة قانونية نافذة.', version: 'الإصدار', manage: 'إدارة السياسات', manageDescription: 'احفظ المسودات ثم انشر النص الذي اعتمدته الجهة المختصة.', legalApproval: 'لا تنشر أي نص قبل اعتماده قانونيًا من الأكاديمية. المسودة لا تظهر للزوار.', arabicDraft: 'المسودة العربية', englishDraft: 'المسودة الإنجليزية', published: 'منشورة', saved: 'حُفظت التغييرات.', publish: 'نشر النسخة المعتمدة', publishConfirm: 'هل تؤكد أن النصين العربي والإنجليزي معتمدان للنشر؟', publicPreview: 'عرض الصفحة العامة' },
    dashboard: { greeting: 'أهلًا، {name}', subtitle: 'كل ما تحتاجه لبدء يومك في JCEC Academy من مكان واحد.', account: 'بيانات الحساب', roles: 'الأدوار', permissions: 'الصلاحيات الفعالة', permissionCount: '{count} صلاحية فعالة', next: 'الوحدات القادمة ستظهر هنا بعد تنفيذها.', publicCatalog: 'تصفح الكتالوج العام', publicCatalogText: 'اطّلع على تجربة الزائر والدورات والباقات المنشورة.', workspace: 'مساحة عملك', email: 'البريد الإلكتروني', profileStatus: 'حالة الحساب' },
    errors: { forbiddenTitle: 'لا تملك صلاحية الوصول', forbiddenText: 'هذه الصفحة تتطلب صلاحيات إضافية.', notFoundTitle: 'الصفحة غير موجودة', notFoundText: 'قد يكون الرابط غير صحيح أو تم نقل الصفحة.', startupTitle: 'تعذر تشغيل التطبيق', startupText: 'لم نتمكن من التحقق من الجلسة. حاول مجددًا.', network: 'تعذر الاتصال بالخادم. تحقق من اتصالك وحاول مجددًا.', server: 'حدث خطأ غير متوقع في الخادم.', generic: 'تعذر إتمام الطلب.' },
    pages: { student: 'مساحة المتدرب', admin: 'لوحة الإدارة', instructor: 'مساحة المدرب', support: 'المبيعات والدعم' },
};
