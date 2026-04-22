import { z } from 'zod';

export const registerSchema = z.object({
  email: z.string().trim().email('올바른 이메일 형식이 아닙니다.'),
  password: z.string().min(8, '비밀번호는 8자 이상이어야 합니다.'),
  display_name: z
    .string()
    .trim()
    .min(1, '표시 이름을 입력해주세요.')
    .max(100, '표시 이름은 100자를 넘을 수 없습니다.'),
});

export type RegisterInput = z.infer<typeof registerSchema>;

export const loginSchema = z.object({
  email: z.string().trim().email('올바른 이메일 형식이 아닙니다.'),
  password: z.string().min(1, '비밀번호를 입력해주세요.'),
});

export type LoginInput = z.infer<typeof loginSchema>;

export const tokenPairSchema = z.object({
  access_token: z.string(),
  refresh_token: z.string(),
  token_type: z.string(),
});

export const meSchema = z.object({
  id: z.number(),
  email: z.string(),
  display_name: z.string(),
  role: z.enum(['USER', 'ADMIN']),
  status: z.enum(['PENDING', 'ACTIVE', 'SUSPENDED']),
});

export type Me = z.infer<typeof meSchema>;
