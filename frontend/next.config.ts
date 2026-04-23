import type { NextConfig } from 'next';

// BUILD_TARGET=dothome 으로 빌드하면 Apache/nginx 위에서 정적 파일로 서빙되는
// 닷홈 같은 호스팅용 번들이 out/ 에 생성된다.
const isDothome = process.env['BUILD_TARGET'] === 'dothome';

const nextConfig: NextConfig = {
  reactStrictMode: true,
  experimental: {
    typedRoutes: true,
  },
  ...(isDothome
    ? {
        output: 'export' as const,
        trailingSlash: true,
        images: { unoptimized: true },
      }
    : {}),
};

export default nextConfig;
