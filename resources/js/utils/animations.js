/**
 * Sistema de Animaciones Modular
 * Variants reutilizables para Framer Motion
 * Inspiración: Apple, Stripe, Shopify Premium
 */

// ============================================
// ANIMATION VARIANTS - Fade
// ============================================

export const fadeIn = {
  hidden: { opacity: 0 },
  visible: { 
    opacity: 1,
    transition: {
      duration: 0.6,
      ease: [0.25, 0.1, 0.25, 1.0], // ease-out suave
    }
  }
};

export const fadeInUp = {
  hidden: { opacity: 0, y: 40 },
  visible: { 
    opacity: 1, 
    y: 0,
    transition: {
      duration: 0.5,
      ease: [0.25, 0.1, 0.25, 1.0],
    }
  }
};

export const fadeInDown = {
  hidden: { opacity: 0, y: -40 },
  visible: { 
    opacity: 1, 
    y: 0,
    transition: {
      duration: 0.5,
      ease: [0.25, 0.1, 0.25, 1.0],
    }
  }
};

export const fadeInLeft = {
  hidden: { opacity: 0, x: -40 },
  visible: { 
    opacity: 1, 
    x: 0,
    transition: {
      duration: 0.5,
      ease: [0.25, 0.1, 0.25, 1.0],
    }
  }
};

export const fadeInRight = {
  hidden: { opacity: 0, x: 40 },
  visible: { 
    opacity: 1, 
    x: 0,
    transition: {
      duration: 0.5,
      ease: [0.25, 0.1, 0.25, 1.0],
    }
  }
};

// ============================================
// ANIMATION VARIANTS - Scale
// ============================================

export const scaleIn = {
  hidden: { opacity: 0, scale: 0.9 },
  visible: { 
    opacity: 1, 
    scale: 1,
    transition: {
      duration: 0.4,
      ease: [0.25, 0.1, 0.25, 1.0],
    }
  }
};

export const scaleInSpring = {
  hidden: { opacity: 0, scale: 0.8 },
  visible: { 
    opacity: 1, 
    scale: 1,
    transition: {
      type: "spring",
      stiffness: 100,
      damping: 15,
    }
  }
};

// ============================================
// ANIMATION VARIANTS - Combinaciones
// ============================================

export const fadeInScaleUp = {
  hidden: { opacity: 0, y: 30, scale: 0.95 },
  visible: { 
    opacity: 1, 
    y: 0, 
    scale: 1,
    transition: {
      duration: 0.5,
      ease: [0.25, 0.1, 0.25, 1.0],
    }
  }
};

// ============================================
// STAGGER CONTAINERS
// ============================================

export const staggerContainer = {
  hidden: { opacity: 0 },
  visible: {
    opacity: 1,
    transition: {
      staggerChildren: 0.1,
      delayChildren: 0.1,
    }
  }
};

export const staggerContainerFast = {
  hidden: { opacity: 0 },
  visible: {
    opacity: 1,
    transition: {
      staggerChildren: 0.05,
      delayChildren: 0.05,
    }
  }
};

export const staggerContainerSlow = {
  hidden: { opacity: 0 },
  visible: {
    opacity: 1,
    transition: {
      staggerChildren: 0.15,
      delayChildren: 0.15,
    }
  }
};

// ============================================
// MICRO-INTERACCIONES - Hover & Tap
// ============================================

export const hoverScale = {
  scale: 1.05,
  transition: {
    duration: 0.2,
    ease: "easeOut",
  }
};

export const hoverScaleSmall = {
  scale: 1.02,
  transition: {
    duration: 0.2,
    ease: "easeOut",
  }
};

export const tapScale = {
  scale: 0.95,
  transition: {
    duration: 0.1,
    ease: "easeOut",
  }
};

export const hoverLift = {
  y: -4,
  transition: {
    duration: 0.2,
    ease: "easeOut",
  }
};

export const hoverGlow = {
  boxShadow: "0 8px 30px rgba(0, 0, 0, 0.12)",
  transition: {
    duration: 0.3,
    ease: "easeOut",
  }
};

// ============================================
// ANIMACIONES ESPECÍFICAS - Cards
// ============================================

export const cardHover = {
  scale: 1.02,
  y: -4,
  boxShadow: "0 20px 40px rgba(0, 0, 0, 0.15)",
  transition: {
    duration: 0.3,
    ease: [0.25, 0.1, 0.25, 1.0],
  }
};

export const cardTap = {
  scale: 0.98,
  transition: {
    duration: 0.1,
    ease: "easeOut",
  }
};

// ============================================
// ANIMACIONES ESPECÍFICAS - Botones
// ============================================

export const buttonHover = {
  scale: 1.05,
  boxShadow: "0 10px 25px rgba(0, 0, 0, 0.15)",
  transition: {
    duration: 0.2,
    ease: "easeOut",
  }
};

export const buttonTap = {
  scale: 0.95,
  transition: {
    duration: 0.1,
    ease: "easeOut",
  }
};

// ============================================
// ANIMACIONES ESPECÍFICAS - Modal
// ============================================

export const modalOverlay = {
  hidden: { opacity: 0 },
  visible: { 
    opacity: 1,
    transition: {
      duration: 0.3,
      ease: "easeOut",
    }
  },
  exit: { 
    opacity: 0,
    transition: {
      duration: 0.2,
      ease: "easeIn",
    }
  }
};

export const modalContent = {
  hidden: { 
    opacity: 0, 
    scale: 0.95,
    y: 20,
  },
  visible: { 
    opacity: 1, 
    scale: 1,
    y: 0,
    transition: {
      duration: 0.3,
      ease: [0.25, 0.1, 0.25, 1.0],
    }
  },
  exit: { 
    opacity: 0,
    scale: 0.95,
    y: 20,
    transition: {
      duration: 0.2,
      ease: "easeIn",
    }
  }
};

// ============================================
// ANIMACIONES ESPECÍFICAS - Dropdown
// ============================================

export const dropdownMenu = {
  hidden: { 
    opacity: 0, 
    scale: 0.95,
    y: -10,
  },
  visible: { 
    opacity: 1, 
    scale: 1,
    y: 0,
    transition: {
      duration: 0.2,
      ease: "easeOut",
    }
  },
  exit: { 
    opacity: 0,
    scale: 0.95,
    y: -10,
    transition: {
      duration: 0.15,
      ease: "easeIn",
    }
  }
};

// ============================================
// ANIMACIONES - Carrusel / Slider
// ============================================

export const slideIn = (direction = 'right') => ({
  hidden: { 
    opacity: 0, 
    x: direction === 'right' ? 100 : -100,
  },
  visible: { 
    opacity: 1, 
    x: 0,
    transition: {
      duration: 0.5,
      ease: [0.25, 0.1, 0.25, 1.0],
    }
  },
  exit: { 
    opacity: 0,
    x: direction === 'right' ? -100 : 100,
    transition: {
      duration: 0.5,
      ease: [0.25, 0.1, 0.25, 1.0],
    }
  }
});

// ============================================
// PRESETS COMBINADOS
// ============================================

export const productCardVariants = {
  hidden: { opacity: 0, y: 30 },
  visible: { 
    opacity: 1, 
    y: 0,
    transition: {
      duration: 0.5,
      ease: [0.25, 0.1, 0.25, 1.0],
    }
  }
};

export const heroVariants = {
  hidden: { opacity: 0, y: 40 },
  visible: { 
    opacity: 1, 
    y: 0,
    transition: {
      duration: 0.8,
      ease: [0.25, 0.1, 0.25, 1.0],
    }
  }
};

export const sectionVariants = {
  hidden: { opacity: 0 },
  visible: { 
    opacity: 1,
    transition: {
      duration: 0.6,
      ease: [0.25, 0.1, 0.25, 1.0],
    }
  }
};
