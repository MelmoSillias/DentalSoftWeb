export function groupPayrollPayments(payments) {
    const groups = new Map();
    for (const payment of payments) {
        const key = `${payment.employeeId}:${payment.year}:${payment.month}:${payment.workedDay || 'month'}`;
        if (!groups.has(key)) groups.set(key, { ...payment, id: key, payments: [], paid: 0, remaining: 0, net: 0 });
        const group = groups.get(key);
        group.payments.push(payment);
        if (payment.status !== 'cancelled') {
            group.paid += Number(payment.paidAmount || 0);
            group.net = Number(payment.calculatedAmount || 0);
        }
    }
    return [...groups.values()]
        .map((group) => ({ ...group, paid: Math.round(group.paid * 100) / 100, remaining: Math.round(Math.max(0, group.net - group.paid) * 100) / 100 }))
        .sort((a, b) => a.employeeName.localeCompare(b.employeeName, 'fr') || String(a.workedDay || '').localeCompare(String(b.workedDay || '')));
}
