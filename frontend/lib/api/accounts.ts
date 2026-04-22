import { apiRequest } from './client';
import {
  type Account,
  accountListSchema,
  accountSchema,
  type CreateAccountInput,
} from '../schemas/accounts';

export async function listAccounts(token: string): Promise<Account[]> {
  const raw = await apiRequest<unknown>('/accounts', { token });
  return accountListSchema.parse(raw).accounts;
}

export async function createAccount(token: string, input: CreateAccountInput): Promise<Account> {
  const body = {
    name: input.name,
    account_type: input.account_type,
    subtype: input.subtype ?? null,
    cash_flow_section: input.cash_flow_section,
    opening_balance:
      input.opening_balance && input.opening_balance !== '' ? input.opening_balance : '0',
  };
  const raw = await apiRequest<unknown>('/accounts', { method: 'POST', body, token });
  return accountSchema.parse(raw);
}

export async function renameAccount(token: string, id: number, name: string): Promise<Account> {
  const raw = await apiRequest<unknown>(`/accounts/${id}`, {
    method: 'PATCH',
    body: { name },
    token,
  });
  return accountSchema.parse(raw);
}

export async function deleteAccount(token: string, id: number): Promise<void> {
  await apiRequest<unknown>(`/accounts/${id}`, { method: 'DELETE', token });
}
