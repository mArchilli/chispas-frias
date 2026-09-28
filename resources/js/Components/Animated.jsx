// Wrappers kept for the existing page structure. Content renders immediately.
export function FadeIn({ children, className = '' }) {
  return <div className={className}>{children}</div>;
}

export function ScaleIn({ children, className = '' }) {
  return <div className={className}>{children}</div>;
}

export function Stagger({ children, className = '' }) {
  return <div className={className}>{children}</div>;
}

export function StaggerItem({ children, className = '' }) {
  return <div className={className}>{children}</div>;
}

export function AnimatedCard({ children, className = '', onClick = null, href = null }) {
  const Component = href ? 'a' : 'div';

  return (
    <Component
      className={className}
      onClick={onClick || undefined}
      href={href || undefined}
      style={onClick ? { cursor: 'pointer' } : undefined}
    >
      {children}
    </Component>
  );
}

export function AnimatedButton({ children, className = '', onClick = null, type = 'button', disabled = false }) {
  return (
    <button type={type} onClick={onClick || undefined} disabled={disabled} className={className}>
      {children}
    </button>
  );
}

export function AnimatedSection({ children, className = '', id = '' }) {
  return <section id={id} className={className}>{children}</section>;
}

export function AnimatedText({ children, className = '', as = 'p' }) {
  const Component = as;
  return <Component className={className}>{children}</Component>;
}

export function AnimatedImage({ src, alt, className = '', wrapperClassName = '', loading = 'lazy' }) {
  return (
    <div className={wrapperClassName}>
      <img src={src} alt={alt} loading={loading} className={className} />
    </div>
  );
}

export function SlideCarousel({ children, className = '' }) {
  return <div className={className}>{children}</div>;
}
