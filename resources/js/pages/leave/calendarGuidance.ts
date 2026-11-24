export function calendarGuidance(
    date: string,
    kind: string,
    days: { date: string }[],
    effectiveFrom: string,
    effectiveTo: string | null,
): string[] {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return [] as string[];
    const selected = new Date(`${date}T00:00:00Z`);
    if (Number.isNaN(selected.getTime())) return [] as string[];
    const messages: string[] = [];
    if (days.some((day) => day.date === date))
        messages.push(
            'Tarikh ini telah wujud dalam draf dan tidak boleh ditambah semula.',
        );
    const weekday = selected.getUTCDay();
    if (weekday === 6)
        messages.push(
            'Tarikh ini hari Sabtu. Tiada cuti gantian Isnin secara automatik.',
        );
    if (weekday === 0) {
        messages.push(
            'Tarikh ini hari Ahad. Semak cuti gantian sebelum pengesahan HR.',
        );
        if (kind === 'holiday') {
            const monday = new Date(selected.getTime() + 86400000);
            const tuesday = new Date(selected.getTime() + 2 * 86400000);
            const dateString = (date: Date) => date.toISOString().slice(0, 10);
            const mondayText = dateString(monday);
            const candidate = days.some((day) => day.date === mondayText)
                ? dateString(tuesday)
                : mondayText;
            if (
                candidate < effectiveFrom ||
                (effectiveTo && candidate > effectiveTo) ||
                days.some((day) => day.date === candidate)
            ) {
                messages.push(
                    'Cadangan Isnin/Selasa tidak tersedia dalam versi ini; semakan HR diperlukan.',
                );
            } else {
                messages.push(
                    `Cadangan untuk semakan HR: ${candidate}. Tiada cuti gantian ditambah secara automatik.`,
                );
            }
        }
    }
    return messages;
}
