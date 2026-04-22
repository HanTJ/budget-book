import { useEffect, useState } from 'react';
import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface AuthState {
  accessToken: string | null;
  refreshToken: string | null;
  setTokens: (tokens: { accessToken: string; refreshToken: string }) => void;
  clear: () => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      accessToken: null,
      refreshToken: null,
      setTokens: ({ accessToken, refreshToken }) => set({ accessToken, refreshToken }),
      clear: () => set({ accessToken: null, refreshToken: null }),
    }),
    { name: 'bb-auth' },
  ),
);

/**
 * Wait for zustand-persist to rehydrate from localStorage before consumers
 * make their first redirect/gate decision. Prevents false-negative "no token"
 * flashes immediately after mount.
 */
export function useAuthHydrated(): boolean {
  // Start false so the SSR pass never claims we have a token — zustand's
  // `persist` plugin is only usable client-side.
  const [hydrated, setHydrated] = useState(false);

  useEffect(() => {
    const persist = useAuthStore.persist;
    if (persist.hasHydrated()) {
      setHydrated(true);
      return;
    }
    const unsub = persist.onFinishHydration(() => setHydrated(true));
    return unsub;
  }, []);

  return hydrated;
}
