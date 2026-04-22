import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import HomePage from '@/app/page';

describe('HomePage', () => {
  it('renders the Budget Book heading', () => {
    render(<HomePage />);
    expect(
      screen.getByRole('heading', { level: 1, name: /budget book/i }),
    ).toBeInTheDocument();
  });

  it('includes a Korean subtitle referencing 가계부', () => {
    render(<HomePage />);
    expect(screen.getByText(/가계부/)).toBeInTheDocument();
  });
});
