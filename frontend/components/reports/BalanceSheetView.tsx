'use client';

import type { BalanceSheet, BalanceSheetLine } from '@/lib/schemas/reports';

interface Props {
  sheet: BalanceSheet;
}

function Section({ title, lines, total }: { title: string; lines: BalanceSheetLine[]; total: string }) {
  return (
    <section className="rounded border">
      <header className="flex items-center justify-between border-b bg-gray-50 px-4 py-2">
        <h3 className="font-semibold">{title}</h3>
        <span className="font-mono text-sm">{total}</span>
      </header>
      <ul>
        {lines.length === 0 ? (
          <li className="px-4 py-2 text-sm text-gray-500">(없음)</li>
        ) : (
          lines.map((line) => (
            <li
              key={`${line.account_type}-${line.account_id}`}
              className="flex items-center justify-between border-b px-4 py-2 last:border-b-0"
            >
              <span>
                {line.name}
                {line.subtype && <span className="ml-2 text-xs text-gray-500">[{line.subtype}]</span>}
              </span>
              <span className="font-mono text-sm">{line.balance}</span>
            </li>
          ))
        )}
      </ul>
    </section>
  );
}

export function BalanceSheetView({ sheet }: Props) {
  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between rounded bg-blue-50 px-4 py-3">
        <div>
          <p className="text-xs text-gray-600">기준일</p>
          <p className="text-lg font-semibold">{sheet.as_of}</p>
        </div>
        <div>
          <p className="text-xs text-gray-600">당기 순이익</p>
          <p className="font-mono text-lg">{sheet.net_income}</p>
        </div>
        <div>
          <p className="text-xs text-gray-600">항등식 (자산 = 부채 + 자본)</p>
          <p className={sheet.is_balanced ? 'text-green-700' : 'text-red-700'}>
            {sheet.is_balanced ? '항등식 성립' : '불균형'}
          </p>
        </div>
      </div>

      <Section title="자산 합계" lines={sheet.assets} total={sheet.total_assets} />
      <Section title="부채 합계" lines={sheet.liabilities} total={sheet.total_liabilities} />
      <Section title="자본 합계" lines={sheet.equity} total={sheet.total_equity} />
    </div>
  );
}
