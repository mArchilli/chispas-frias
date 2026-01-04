/**
 * Custom Hooks para Animaciones
 * Optimizados para performance y accesibilidad
 */

import { useEffect, useState } from 'react';
import { useInView } from 'framer-motion';

/**
 * Hook para detectar si el usuario prefiere movimiento reducido
 * Respeta la configuración de accesibilidad del sistema
 */
export function useReducedMotion() {
  const [prefersReducedMotion, setPrefersReducedMotion] = useState(false);

  useEffect(() => {
    const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    setPrefersReducedMotion(mediaQuery.matches);

    const handleChange = (e) => {
      setPrefersReducedMotion(e.matches);
    };

    mediaQuery.addEventListener('change', handleChange);
    return () => mediaQuery.removeEventListener('change', handleChange);
  }, []);

  return prefersReducedMotion;
}

/**
 * Hook para animaciones de scroll
 * Retorna props optimizadas para motion components
 */
export function useScrollAnimation(options = {}) {
  const {
    threshold = 0.1,
    triggerOnce = true,
    amount = 0.3,
  } = options;

  const reducedMotion = useReducedMotion();

  return {
    initial: reducedMotion ? 'visible' : 'hidden',
    whileInView: 'visible',
    viewport: { 
      once: triggerOnce, 
      amount: amount,
      margin: '0px 0px -100px 0px' // Trigger antes de que sea visible
    },
  };
}

/**
 * Hook para detectar viewport size (mobile vs desktop)
 */
export function useViewportSize() {
  const [isMobile, setIsMobile] = useState(false);
  const [isTablet, setIsTablet] = useState(false);
  const [isDesktop, setIsDesktop] = useState(false);

  useEffect(() => {
    const handleResize = () => {
      const width = window.innerWidth;
      setIsMobile(width < 768);
      setIsTablet(width >= 768 && width < 1024);
      setIsDesktop(width >= 1024);
    };

    handleResize();
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  return { isMobile, isTablet, isDesktop };
}

/**
 * Hook para lazy loading de imágenes con placeholder
 */
export function useImageLoader(src) {
  const [imageSrc, setImageSrc] = useState(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const img = new Image();
    img.src = src;
    
    img.onload = () => {
      setImageSrc(src);
      setIsLoading(false);
    };

    img.onerror = () => {
      setIsLoading(false);
    };
  }, [src]);

  return { imageSrc, isLoading };
}

/**
 * Hook para stagger delays calculados dinámicamente
 */
export function useStaggerDelay(index, baseDelay = 0.1) {
  return index * baseDelay;
}
