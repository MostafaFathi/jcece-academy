export function validScore(score, maximum) {
    const decimal = /^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/;
    if (!decimal.test(String(score)) || !decimal.test(String(maximum))) return false;
    const cents = (value) => {
        const [whole, fraction = ''] = String(value).split('.');
        return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
    };
    return cents(score) <= cents(maximum);
}
