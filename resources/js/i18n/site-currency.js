export const ar = {
    title: 'عملة الموقع', current: 'العملة الافتراضية الحالية', target: 'العملة الجديدة', scope: 'نطاق التغيير',
    future: 'للعناصر الجديدة فقط', futureHint: 'تبقى أسعار الدورات والباقات والكوبونات الحالية وعملاتها كما هي. تُنشأ العناصر الجديدة بالعملة المختارة.',
    all: 'تحويل الأسعار الحالية أيضًا', allHint: 'تحويل أسعار الدورات والباقات والكوبونات الحالية بأسعار الصرف التي تدخلها. الطلبات والمدفوعات والفواتير السابقة لا تتغير.',
    historyHint: 'لا يتم تغيير مبالغ أو عملات الطلبات والمدفوعات والاستردادات والفواتير السابقة. قد تظهر أكثر من عملة في التقارير بعد التغيير، ولا تُجمع في إجمالي واحد.',
    rate: 'سعر تحويل {source} إلى {target}', rateHint: 'كم تساوي وحدة واحدة من {source} بعملة {target}؟',
    confirm: 'هل أنت متأكد؟ سيُطبق تغيير العملة فورًا، ولا يمكن التراجع عن تحويل الأسعار تلقائيًا.',
    apply: 'تطبيق تغيير العملة', saving: 'جارٍ التطبيق…', saved: 'تم تغيير عملة الموقع.',
    converted: 'الدورات: {courses}، الباقات: {packages}، الكوبونات: {coupons}.', error: 'تعذر تغيير العملة.',
    codes: { ILS: 'شيكل إسرائيلي', USD: 'دولار أمريكي', JOD: 'دينار أردني', EUR: 'يورو', GBP: 'جنيه إسترليني', AED: 'درهم إماراتي', SAR: 'ريال سعودي', EGP: 'جنيه مصري', TRY: 'ليرة تركية' },
};

export const en = {
    title: 'Site currency', current: 'Current default currency', target: 'New currency', scope: 'Change scope',
    future: 'New items only', futureHint: 'Existing courses, packages and coupons keep their prices and currencies. New items use the selected currency.',
    all: 'Convert current prices too', allHint: 'Convert existing course, package and coupon amounts using rates you provide. Historical orders, payments and invoices remain unchanged.',
    historyHint: 'Historical order, payment, refund and invoice amounts and currencies never change. Reports may contain multiple currencies and do not combine them into one total.',
    rate: '{source} to {target} exchange rate', rateHint: 'How many {target} equal one {source}?',
    confirm: 'Apply this currency change now? Price conversions cannot be reversed automatically.',
    apply: 'Apply currency change', saving: 'Applying…', saved: 'Site currency changed.',
    converted: 'Courses: {courses}, packages: {packages}, coupons: {coupons}.', error: 'Could not change the currency.',
    codes: { ILS: 'Israeli shekel', USD: 'US dollar', JOD: 'Jordanian dinar', EUR: 'Euro', GBP: 'British pound', AED: 'UAE dirham', SAR: 'Saudi riyal', EGP: 'Egyptian pound', TRY: 'Turkish lira' },
};
