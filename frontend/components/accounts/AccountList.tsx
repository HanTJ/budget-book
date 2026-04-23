'use client';

import type { Account, AccountType } from '@/lib/schemas/accounts';

interface Props {
  accounts: Account[];
  onDelete: (id: number) => void;
}

const TYPE_LABEL: Record<AccountType, string> = {
  ASSET: '자산',
  LIABILITY: '부채',
  EQUITY: '자본',
  INCOME: '수익',
  EXPENSE: '비용',
};

const ORDER: AccountType[] = ['ASSET', 'LIABILITY', 'EQUITY', 'INCOME', 'EXPENSE'];

export function AccountList({ accounts, onDelete }: Props) {
  const grouped = ORDER.map((type) => ({
    type,
    label: TYPE_LABEL[type],
    items: accounts.filter((a) => a.account_type === type),
  })).filter((g) => g.items.length > 0);

  if (accounts.length === 0) {
    return <p className="text-gray-600">아직 계정이 없습니다. 아래에서 추가해주세요.</p>;
  }

  return (
    <div className="flex flex-col gap-4">
      {grouped.map((group) => (
        <section key={group.type} className="rounded border">
          <header className="border-b bg-gray-50 px-4 py-2 font-semibold">{group.label}</header>
          <ul>
            {group.items.map((account) => (
              <li
                key={account.id}
                className="flex items-center justify-between border-b px-4 py-2 last:border-b-0"
              >
                <div>
                  <span>{account.name}</span>
                  {account.subtype && (
                    <span className="ml-2 text-xs text-gray-500">[{account.subtype}]</span>
                  )}
                  {account.cash_flow_section !== 'NONE' && (
                    <span className="ml-2 text-xs text-blue-600">{account.cash_flow_section}</span>
                  )}
                  {account.is_system && <span className="ml-2 text-xs text-gray-400">기본</span>}
                </div>
                <div className="flex items-center gap-3 text-sm">
                  <span className="text-gray-600">{account.opening_balance}</span>
                  {!account.is_system && (
                    <button
                      type="button"
                      onClick={() => onDelete(account.id)}
                      className="rounded border px-2 py-1 text-red-600"
                    >
                      삭제
                    </button>
                  )}
                </div>
              </li>
            ))}
          </ul>
        </section>
      ))}
    </div>
  );
}
