import { apiRequest } from './client';
import {
  type JournalEntry,
  journalEntryListSchema,
  journalEntrySchema,
  type RecordEntryInput,
} from '../schemas/entries';

export async function listEntries(
  token: string,
  from: string,
  to: string,
): Promise<JournalEntry[]> {
  const raw = await apiRequest<unknown>(
    `/entries?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`,
    { token },
  );
  return journalEntryListSchema.parse(raw).entries;
}

export async function recordEntry(
  token: string,
  input: RecordEntryInput,
): Promise<JournalEntry> {
  const body: Record<string, unknown> = {
    occurred_on: input.occurred_on,
    amount: input.amount,
    payment_method: input.payment_method,
    category_account_id: input.category_account_id,
  };
  if (input.counter_account_id !== undefined) body['counter_account_id'] = input.counter_account_id;
  if (input.merchant !== undefined && input.merchant !== '') body['merchant'] = input.merchant;
  if (input.memo !== undefined && input.memo !== '') body['memo'] = input.memo;

  const raw = await apiRequest<unknown>('/entries', { method: 'POST', body, token });
  return journalEntrySchema.parse(raw);
}

export async function deleteEntry(token: string, id: number): Promise<void> {
  await apiRequest<unknown>(`/entries/${id}`, { method: 'DELETE', token });
}
