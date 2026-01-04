/**
 * Componentes Wrapper para Animaciones
 * Facilitan la implementación de animaciones en todo el sitio
 */

import { motion } from 'framer-motion';
import { useScrollAnimation, useReducedMotion } from '@/hooks/useAnimations';
import * as animations from '@/utils/animations';

/**
 * FadeIn - Componente para animaciones de aparición con fade
 */
export function FadeIn({ children, direction = 'up', delay = 0, className = '' }) {
  const scrollProps = useScrollAnimation();
  const reducedMotion = useReducedMotion();

  const variants = {
    up: animations.fadeInUp,
    down: animations.fadeInDown,
    left: animations.fadeInLeft,
    right: animations.fadeInRight,
    none: animations.fadeIn,
  };

  const selectedVariant = variants[direction] || animations.fadeInUp;

  // Si el usuario prefiere movimiento reducido, aplicar fade simple
  const finalVariant = reducedMotion ? animations.fadeIn : selectedVariant;

  return (
    <motion.div
      {...scrollProps}
      variants={finalVariant}
      transition={{ delay }}
      className={className}
    >
      {children}
    </motion.div>
  );
}

/**
 * ScaleIn - Componente para animaciones de escala
 */
export function ScaleIn({ children, delay = 0, spring = false, className = '' }) {
  const scrollProps = useScrollAnimation();
  
  const variant = spring ? animations.scaleInSpring : animations.scaleIn;

  return (
    <motion.div
      {...scrollProps}
      variants={variant}
      transition={{ delay }}
      className={className}
    >
      {children}
    </motion.div>
  );
}

/**
 * Stagger - Container para animaciones escalonadas
 */
export function Stagger({ children, speed = 'normal', className = '' }) {
  const scrollProps = useScrollAnimation();

  const containers = {
    fast: animations.staggerContainerFast,
    normal: animations.staggerContainer,
    slow: animations.staggerContainerSlow,
  };

  const selectedContainer = containers[speed] || animations.staggerContainer;

  return (
    <motion.div
      {...scrollProps}
      variants={selectedContainer}
      className={className}
    >
      {children}
    </motion.div>
  );
}

/**
 * StaggerItem - Item individual dentro de un Stagger container
 */
export function StaggerItem({ children, className = '' }) {
  return (
    <motion.div
      variants={animations.fadeInUp}
      className={className}
    >
      {children}
    </motion.div>
  );
}

/**
 * AnimatedCard - Card con animaciones de hover y aparición
 */
export function AnimatedCard({ 
  children, 
  className = '', 
  onClick = null,
  href = null,
  hoverEffect = true,
}) {
  const scrollProps = useScrollAnimation();
  const reducedMotion = useReducedMotion();

  const cardProps = {
    ...scrollProps,
    variants: animations.productCardVariants,
    className,
  };

  // Solo agregar hover/tap si no hay movimiento reducido
  if (hoverEffect && !reducedMotion) {
    cardProps.whileHover = animations.cardHover;
    cardProps.whileTap = animations.cardTap;
  }

  if (onClick) {
    cardProps.onClick = onClick;
    cardProps.style = { cursor: 'pointer' };
  }

  const Component = href ? motion.a : motion.div;
  if (href) {
    cardProps.href = href;
  }

  return (
    <Component {...cardProps}>
      {children}
    </Component>
  );
}

/**
 * AnimatedButton - Botón con micro-interacciones
 */
export function AnimatedButton({ 
  children, 
  className = '', 
  onClick = null,
  type = 'button',
  disabled = false,
  variant = 'default',
}) {
  const reducedMotion = useReducedMotion();

  const buttonProps = {
    type,
    onClick,
    disabled,
    className,
  };

  // Solo agregar animaciones si no hay movimiento reducido
  if (!reducedMotion && !disabled) {
    buttonProps.whileHover = animations.buttonHover;
    buttonProps.whileTap = animations.buttonTap;
  }

  return (
    <motion.button {...buttonProps}>
      {children}
    </motion.button>
  );
}

/**
 * AnimatedSection - Sección completa con animación de aparición
 */
export function AnimatedSection({ children, className = '', id = '' }) {
  const scrollProps = useScrollAnimation({ amount: 0.2 });

  return (
    <motion.section
      id={id}
      {...scrollProps}
      variants={animations.sectionVariants}
      className={className}
    >
      {children}
    </motion.section>
  );
}

/**
 * AnimatedText - Texto con animación de aparición
 */
export function AnimatedText({ 
  children, 
  className = '', 
  delay = 0,
  variant = 'fadeInUp',
  as = 'p',
}) {
  const scrollProps = useScrollAnimation();
  const Component = motion[as] || motion.p;

  const variants = {
    fadeIn: animations.fadeIn,
    fadeInUp: animations.fadeInUp,
    fadeInDown: animations.fadeInDown,
    fadeInLeft: animations.fadeInLeft,
    fadeInRight: animations.fadeInRight,
  };

  return (
    <Component
      {...scrollProps}
      variants={variants[variant] || animations.fadeInUp}
      transition={{ delay }}
      className={className}
    >
      {children}
    </Component>
  );
}

/**
 * AnimatedImage - Imagen con animación de carga y aparición
 */
export function AnimatedImage({ 
  src, 
  alt, 
  className = '',
  wrapperClassName = '',
  loading = 'lazy',
}) {
  const scrollProps = useScrollAnimation();

  return (
    <motion.div
      {...scrollProps}
      variants={animations.scaleIn}
      className={wrapperClassName}
    >
      <motion.img
        src={src}
        alt={alt}
        loading={loading}
        className={className}
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ duration: 0.5 }}
      />
    </motion.div>
  );
}

/**
 * SlideCarousel - Wrapper para elementos de carrusel con animaciones
 */
export function SlideCarousel({ children, direction = 'right', className = '' }) {
  return (
    <motion.div
      variants={animations.slideIn(direction)}
      initial="hidden"
      animate="visible"
      exit="exit"
      className={className}
    >
      {children}
    </motion.div>
  );
}
