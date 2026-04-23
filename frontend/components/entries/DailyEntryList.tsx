'use client';

import type { JournalEntry } from '@/lib/schemas/entries';

interface Props {
  entries: JournalEntry[];
  onDelete: (id: number) => void;
}

export function DailyEntryList({ entries, onDelete }: Props) {
  if (entries.length === 0) {
    return <p className="text-gray-600">아직 거래가 없습니다.</p>;
  }

  const byDate = new Map<string, JournalEntry[]>();
  for (const entry of entries) {
    const list = byDate.get(entry.occurred_on) ?? [];
    list.push(entry);
    byDate.set(entry.occurred_on, list);
  }
  const dates = Array.from(byDate.keys()).sort((a, b) => (a < b ? 1 : -1));

  return (
    <div className="flex flex-col gap-4">
      {dates.map((date) => (
        <section key={date} className="rounded border">
          <h3 className="border-b bg-gray-50 px-4 py-2 font-semibold">{date}</h3>
          <ul>
            {(byDate.get(date) ?? []).map((entry) => (
              <li
                key={entry.id}
                className="flex items-center justify-between border-b px-4 py-2 last:border-b-0"
              >
                <div className="flex flex-col">
                  <span>{entry.merchant ?? '(사용처 없음)'}</span>
                  {entry.memo && <span className="text-xs text-gray-500">{entry.memo}</span>}
                  <span className="text-xs text-gray-500">{entry.payment_method ?? '—'}</span>
                </div>
                <div className="flex items-center gap-3 text-sm">
                  <span>{entry.amount}</span>
                  <button
                    type="button"
                    onClick={() => onDelete(entry.id)}
                    className="rounded border px-2 py-1 text-red-600"
                  >
                    삭제
                  </button>
                </div>
              </li>
            ))}
          </ul>
        </section>
      ))}
    </div>
  );
}
