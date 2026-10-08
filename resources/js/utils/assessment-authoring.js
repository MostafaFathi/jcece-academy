export const questionTypes = ['single_choice', 'multiple_choice', 'true_false'];
export const submissionTypes = ['text', 'file', 'text_and_file'];

export function emptyQuestion(type = 'single_choice') {
    return { id: null, type, question_text: '', explanation: '', points: 1, options: [
        { answer_text: type === 'true_false' ? 'True' : '', is_correct: type === 'true_false' },
        { answer_text: type === 'true_false' ? 'False' : '', is_correct: false },
    ] };
}

export function questionForEditing(question) {
    return {
        id: question.id,
        type: question.type,
        question_text: question.question_text ?? '',
        explanation: question.explanation ?? '',
        points: Number(question.points),
        options: question.options.map(({ answer_text, is_correct }) => ({ answer_text, is_correct: Boolean(is_correct) })),
    };
}

export function validateQuestion(question) {
    const errors = {};
    if (!questionTypes.includes(question.type)) errors.type = 'invalidType';
    if (!question.question_text.trim()) errors.question_text = 'questionRequired';
    if (!Number.isFinite(Number(question.points)) || Number(question.points) <= 0 || Number(question.points) > 999999.99 || !/^\d+(\.\d{1,2})?$/.test(String(question.points))) errors.points = 'pointsInvalid';
    const options = question.options;
    if (options.length < 2 || options.some((option) => !option.answer_text.trim()) || new Set(options.map((option) => option.answer_text.trim().toLocaleLowerCase())).size !== options.length) errors.options = 'optionsInvalid';
    const correctCount = options.filter((option) => option.is_correct).length;
    if (question.type === 'true_false' && options.length !== 2) errors.options = 'optionsInvalid';
    if (question.type === 'single_choice' || question.type === 'true_false') {
        if (correctCount !== 1) errors.options = 'oneCorrectRequired';
    } else if (question.type === 'multiple_choice' && correctCount < 1) errors.options = 'correctRequired';
    return errors;
}

export function questionPayload(question) {
    const options = question.options.map(({ answer_text, is_correct }) => ({ answer_text: answer_text.trim(), is_correct: Boolean(is_correct) }));
    return { type: question.type, question_text: question.question_text.trim(), explanation: question.explanation.trim() || null, points: Number(question.points), options };
}

export function toLocalDateTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

export function toServerDateTime(value) {
    return value ? new Date(value).toISOString() : null;
}

export function validateDefinition(form, kind) {
    const errors = {};
    if (!form.title.trim()) errors.title = 'titleRequired';
    const scoreValue = String(kind === 'quiz' ? form.passing_score : form.maximum_score);
    const score = Number(scoreValue);
    if (!/^\d+(\.\d{1,2})?$/.test(scoreValue) || !Number.isFinite(score) || (kind === 'quiz' ? score > 100 : score <= 0 || score > 99999999.99)) errors[kind === 'quiz' ? 'passing_score' : 'maximum_score'] = 'scoreInvalid';
    if (form.max_attempts !== '' && (!Number.isInteger(Number(form.max_attempts)) || Number(form.max_attempts) < 1 || Number(form.max_attempts) > 1000)) errors.max_attempts = 'attemptsInvalid';
    if (kind === 'quiz' && form.time_limit_minutes !== '' && (!Number.isInteger(Number(form.time_limit_minutes)) || Number(form.time_limit_minutes) < 1 || Number(form.time_limit_minutes) > 10080)) errors.time_limit_minutes = 'timeInvalid';
    if (kind === 'assignment' && form.passing_score !== '' && (!/^\d+(\.\d{1,2})?$/.test(String(form.passing_score)) || Number(form.passing_score) > Number(form.maximum_score))) errors.passing_score = 'passingInvalid';
    const end = kind === 'quiz' ? form.available_until : form.due_at;
    if (form.available_from && end && new Date(end) <= new Date(form.available_from)) errors[kind === 'quiz' ? 'available_until' : 'due_at'] = 'dateInvalid';
    return errors;
}
