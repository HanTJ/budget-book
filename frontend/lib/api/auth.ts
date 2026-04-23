import { apiRequest } from './client';
import {
  type LoginInput,
  type Me,
  meSchema,
  type RegisterInput,
  tokenPairSchema,
} from '../schemas/auth';

export interface RegisteredUser {
  id: number;
  email: string;
  status: 'PENDING' | 'ACTIVE' | 'SUSPENDED';
}

export async function register(input: RegisterInput): Promise<RegisteredUser> {
  return apiRequest<RegisteredUser>('/auth/register', {
    method: 'POST',
    body: input,
  });
}

export async function login(
  input: LoginInput,
): Promise<{ accessToken: string; refreshToken: string }> {
  const raw = await apiRequest<unknown>('/auth/login', {
    method: 'POST',
    body: input,
  });
  const parsed = tokenPairSchema.parse(raw);
  return { accessToken: parsed.access_token, refreshToken: parsed.refresh_token };
}

export async function me(token: string): Promise<Me> {
  const raw = await apiRequest<unknown>('/me', { token });
  return meSchema.parse(raw);
}
