export const ar = {
    days: 'يومًا',
    all: 'الكل',
    period: 'التجميع الزمني', day: 'يوميًا', month: 'شهريًا', trend: 'اتجاه المبيعات',
    statuses: { pending_review: 'بانتظار المراجعة', paid: 'مدفوع', rejected: 'مرفوض', pending: 'معلق', completed: 'مكتمل', active: 'فعال', suspended: 'موقوف', draft: 'مسودة', published: 'منشور', hidden: 'مخفي', coming_soon: 'قريبًا', archived: 'مؤرشف' },
    methods: { bank_transfer: 'تحويل بنكي', wallet: 'محفظة', manual: 'يدوي' }, sources: { direct_purchase: 'شراء مباشر', package_purchase: 'باقة', admin: 'منحة إدارية', free: 'مجاني', promotion: 'ترويجي' },
    title: 'التقارير', intro: 'تقارير تشغيلية من بيانات محفوظة، وليست قوائم مالية مدققة.',
    package_access_learners: 'متعلمون عبر الباقات', direct_sales: 'مبيعات مباشرة',
    completion_percentage: 'نسبة الإكمال', pass_rate_percentage: 'معدل النجاح',
    sales: 'المبيعات', payments: 'المدفوعات', refunds: 'الاستردادات', courses: 'الدورات', learners: 'المتدربون', instructors: 'المدربون', reviews: 'التقييمات', quizzes: 'الاختبارات', packages: 'الباقات', coupons: 'الكوبونات',
    from: 'من تاريخ', to: 'إلى تاريخ', courseId: 'رقم الدورة', packageId: 'رقم الباقة', instructorId: 'رقم المدرب', currency: 'العملة', apply: 'عرض التقرير', export: 'تصدير', noData: 'لا توجد بيانات لهذه المرشحات.', loadError: 'تعذر تحميل التقرير.', exportError: 'تعذر تصدير التقرير. قلّل نطاق البيانات إن كان كبيرًا.', timezone: 'التواريخ حسب توقيت الخليل؛ بداية الفترة شاملة ونهايتها تشمل اليوم المختار.',
    gross_sales: 'إجمالي المبيعات', discounts: 'الخصومات', refunds: 'الاستردادات المكتملة', net_sales: 'صافي المبيعات', order_count: 'الطلبات المكتملة', zero_total_orders: 'طلبات بلا مبلغ', average_order_value: 'متوسط الطلب',
    order_number: 'رقم الطلب', paid_at: 'تاريخ الدفع', total: 'الإجمالي', discount_total: 'الخصم', coupon_code_snapshot: 'الكوبون', method: 'الطريقة', status: 'الحالة', amount: 'المبلغ', created_at: 'تاريخ الإنشاء', processed_at: 'تاريخ المعالجة', titleField: 'العنوان', instructor_name: 'المدرب', enrollments: 'التسجيلات', learnersField: 'المتعلمون', completions: 'الإكمالات', rating_count: 'عدد التقييمات', rating_average: 'متوسط التقييم', course_title: 'الدورة', learner_name: 'المتدرب', enrolled_at: 'تاريخ التسجيل', completed_at: 'تاريخ الإكمال', access_source: 'مصدر الوصول', access_active: 'وصول فعال', access_expires_at: 'انتهاء الوصول', completed_lessons: 'دروس مكتملة', progress_percentage: 'نسبة التقدم', name: 'الاسم', coursesField: 'الدورات', five_star_count: 'خمس نجوم', quiz_title: 'الاختبار', attempts: 'المحاولات', passed: 'الناجحة', average_percentage: 'متوسط النتيجة', package_title: 'الباقة', purchases: 'المشتريات', gross_sales_row: 'إجمالي البيع', coupon_code: 'رمز الكوبون', redemptions: 'مرات الاستخدام', coupon_discount: 'خصم الكوبون', id: 'الرقم', currencyField: 'العملة',
};

export const en = {
    days: 'days',
    all: 'All',
    period: 'Group by', day: 'Day', month: 'Month', trend: 'Sales trend',
    statuses: { pending_review: 'Pending review', paid: 'Paid', rejected: 'Rejected', pending: 'Pending', completed: 'Completed', active: 'Active', suspended: 'Suspended', draft: 'Draft', published: 'Published', hidden: 'Hidden', coming_soon: 'Coming soon', archived: 'Archived' },
    methods: { bank_transfer: 'Bank transfer', wallet: 'Wallet', manual: 'Manual' }, sources: { direct_purchase: 'Direct purchase', package_purchase: 'Package', admin: 'Admin grant', free: 'Free', promotion: 'Promotion' },
    title: 'Reports', intro: 'Operational reports from persisted records, not audited financial statements.',
    package_access_learners: 'Package-access learners', direct_sales: 'Direct sales',
    completion_percentage: 'Completion %', pass_rate_percentage: 'Pass rate %',
    sales: 'Sales', payments: 'Payments', refunds: 'Refunds', courses: 'Courses', learners: 'Learners', instructors: 'Instructors', reviews: 'Ratings', quizzes: 'Quizzes', packages: 'Packages', coupons: 'Coupons',
    from: 'From', to: 'To', courseId: 'Course ID', packageId: 'Package ID', instructorId: 'Instructor ID', currency: 'Currency', apply: 'Apply filters', export: 'Export', noData: 'No data matches these filters.', loadError: 'Unable to load report.', exportError: 'Unable to export. Narrow the range if it is too large.', timezone: 'Dates use Hebron time; the start is included and the selected end day is included.',
    gross_sales: 'Gross sales', discounts: 'Discounts', refunds: 'Completed refunds', net_sales: 'Net sales', order_count: 'Completed orders', zero_total_orders: 'Zero-total orders', average_order_value: 'Average order value',
    order_number: 'Order', paid_at: 'Paid at', total: 'Total', discount_total: 'Discount', coupon_code_snapshot: 'Coupon', method: 'Method', status: 'Status', amount: 'Amount', created_at: 'Created at', processed_at: 'Processed at', titleField: 'Title', instructor_name: 'Instructor', enrollments: 'Enrollments', learnersField: 'Learners', completions: 'Completions', rating_count: 'Rating count', rating_average: 'Average rating', course_title: 'Course', learner_name: 'Learner', enrolled_at: 'Enrolled at', completed_at: 'Completed at', access_source: 'Access source', access_active: 'Active access', access_expires_at: 'Access expiry', completed_lessons: 'Completed lessons', progress_percentage: 'Progress %', name: 'Name', coursesField: 'Courses', five_star_count: 'Five stars', quiz_title: 'Quiz', attempts: 'Attempts', passed: 'Passed', average_percentage: 'Average score', package_title: 'Package', purchases: 'Purchases', gross_sales_row: 'Gross sales', coupon_code: 'Coupon code', redemptions: 'Redemptions', coupon_discount: 'Coupon discount', id: 'ID', currencyField: 'Currency',
};
