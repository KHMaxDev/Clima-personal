import { motion, useReducedMotion } from 'motion/react';

/**
 * Reveal reutilizable basado en Motion. Anima la entrada del contenido cuando
 * entra en el viewport. Si el usuario prefiere movimiento reducido, se renderiza
 * el elemento sin animación ni estado inicial oculto.
 */
export default function Reveal({
    as = 'div',
    children,
    className,
    delay = 0,
    y = 24,
    once = true,
    amount = 0.2,
    ...rest
}) {
    const reduceMotion = useReducedMotion();
    const Tag = as;

    if (reduceMotion) {
        return (
            <Tag className={className} {...rest}>
                {children}
            </Tag>
        );
    }

    const MotionTag = motion[as] ?? motion.div;

    return (
        <MotionTag
            className={className}
            initial={{ opacity: 0, y }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once, amount }}
            transition={{ duration: 0.6, delay, ease: [0.22, 1, 0.36, 1] }}
            {...rest}
        >
            {children}
        </MotionTag>
    );
}
