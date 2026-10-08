export const ar = {
    threshold: 'نسبة إكمال الدروس المطلوبة',
    thresholdHint: 'ترتيب الدورات يحدد فتح الدورة التالية. لا تعتمد هذه الخطوة على الشهادة أو موافقة الإدارة.',
    thresholdInvalid: 'أدخل نسبة أكبر من 0 ولا تتجاوز 100، وبحد أقصى منزلتين عشريتين.',
    courseOrder: 'الدورة {current} من {total} في المسار',
    ownedLocked: 'مملوكة، لكنها مقفلة حتى تتقدم في الدورة السابقة.',
    previousProgress: 'تقدمك في {course}: {progress}٪؛ المطلوب {required}٪.',
    unlockRule: 'تفتح الدورة التالية عند إكمال {required}٪ من دروس الدورة السابقة، خلال مدة الوصول الأصلية للباقة.',
    legacyConfiguration: 'يجب ضبط نسبة التقدم قبل شراء هذا المسار المتسلسل.',
};

export const en = {
    threshold: 'Required lesson completion percentage',
    thresholdHint: 'Course order controls unlocking. Certificates and administrator approval are not required.',
    thresholdInvalid: 'Enter a percentage above 0 and at most 100, with up to two decimal places.',
    courseOrder: 'Course {current} of {total} in this path',
    ownedLocked: 'Owned, but locked until you progress through the preceding course.',
    previousProgress: 'Progress in {course}: {progress}%; required {required}%.',
    unlockRule: 'The next course unlocks after {required}% of the preceding course’s lessons, within the original package access period.',
    legacyConfiguration: 'A progression threshold must be set before this sequential path can be purchased.',
};
