import { z } from 'zod';

export const adminUserSchema = z.object({
  id: z.number(),
  email: z.string(),
  display_name: z.string(),
  role: z.enum(['USER', 'ADMIN']),
  status: z.enum(['PENDING', 'ACTIVE', 'SUSPENDED']),
  created_at: z.string(),
});
export type AdminUser = z.infer<typeof adminUserSchema>;

export const adminUserListSchema = z.object({
  users: z.array(adminUserSchema),
});
