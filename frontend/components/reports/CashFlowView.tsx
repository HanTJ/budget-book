'use client';

import type { CashFlowStatement } from '@/lib/schemas/reports';

interface Props {
  statement: CashFlowStatement;
}

function SectionRow({
  title,
  inflow,
  outflow,
  net,
}: {
  title: string;
  inflow: string;
  outflow: string;
  net: string;
}) {
  return (
    <tr className="border-b last:border-b-0">
      <th className="px-4 py-2 text-left font-semibold">{title}</th>
      <td className="px-4 py-2 text-right font-mono text-green-700">+ {inflow}</td>
      <td className="px-4 py-2 text-right font-mono text-red-700">− {outflow}</td>
      <td className="px-4 py-2 text-right font-mono">{net}</td>
    </tr>
  );
}

export function CashFlowView({ statement }: Props) {
  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between rounded bg-blue-50 px-4 py-3">
        <div>
          <p className="text-xs text-gray-600">기간</p>
          <p className="font-mono text-sm">
            {statement.from} ~ {statement.to}
          </p>
        </div>
        <div>
          <p className="text-xs text-gray-600">기초 현금</p>
          <p className="font-mono">{statement.opening_cash_balance}</p>
        </div>
        <div>
          <p className="text-xs text-gray-600">기말 현금</p>
          <p className="font-mono">{statement.closing_cash_balance}</p>
        </div>
        <div>
          <p className="text-xs text-gray-600">조정 검증</p>
          <p className={statement.is_reconciled ? 'text-green-700' : 'text-red-700'}>
            {statement.is_reconciled ? '조정 일치' : '불일치'}
          </p>
        </div>
      </div>

      <table className="w-full rounded border">
        <thead className="bg-gray-50">
          <tr>
            <th className="px-4 py-2 text-left">섹션</th>
            <th className="px-4 py-2 text-right">유입</th>
            <th className="px-4 py-2 text-right">유출</th>
            <th className="px-4 py-2 text-right">순증감</th>
          </tr>
        </thead>
        <tbody>
          <SectionRow
            title="영업활동"
            inflow={statement.operating.inflow}
            outflow={statement.operating.outflow}
            net={statement.operating.net}
          />
          <SectionRow
            title="투자활동"
            inflow={statement.investing.inflow}
            outflow={statement.investing.outflow}
            net={statement.investing.net}
          />
          <SectionRow
            title="재무활동"
            inflow={statement.financing.inflow}
            outflow={statement.financing.outflow}
            net={statement.financing.net}
          />
          <tr className="bg-gray-50 font-semibold">
            <th className="px-4 py-2 text-left">순현금증감</th>
            <td />
            <td />
            <td className="px-4 py-2 text-right font-mono">{statement.net_change}</td>
          </tr>
        </tbody>
      </table>
    </div>
  );
}
