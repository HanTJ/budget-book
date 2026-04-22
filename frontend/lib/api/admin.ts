import { apiRequest } from './client';
import { type AdminUser, adminUserListSchema, adminUserSchema } from '../schemas/admin';

export async function listAdminUsers(
  token: string,
  status?: 'PENDING' | 'ACTIVE' | 'SUSPENDED',
): Promise<AdminUser[]> {
  const qs = status ? `?status=${status}` : '';
  const raw = await apiRequest<unknown>(`/admin/users${qs}`, { token });
  return adminUserListSchema.parse(raw).users;
}

export async function patchAdminUser(
  token: string,
  id: number,
  changes: { status?: 'ACTIVE' | 'SUSPENDED'; role?: 'USER' | 'ADMIN' },
): Promise<AdminUser> {
  const body: Record<string, unknown> = {};
  if (changes.status) body['status'] = changes.status;
  if (changes.role) body['role'] = changes.role;
  const raw = await apiRequest<unknown>(`/admin/users/${id}`, { method: 'PATCH', body, token });
  return adminUserSchema.parse(raw);
}

export async function deleteAdminUser(token: string, id: number): Promise<void> {
  await apiRequest<unknown>(`/admin/users/${id}`, { method: 'DELETE', token });
}
