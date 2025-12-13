import { memo, type ComponentType, type KeyboardEvent, type ReactNode } from 'react';
import Tooltip from './Tooltip/Tooltip';
import type { TooltipProps } from './Tooltip/Tooltip';
import customIcons from './customIcons';
import materialIcons from './materialIcons';

// Color mapping from our custom colors to CSS variables
const iconColors = {
  black: 'var(--teamupdraft-black)',
  green: 'var(--teamupdraft-green)',
  yellow: 'var(--teamupdraft-yellow)',
  red: 'var(--teamupdraft-red)',
  blue: 'var(--teamupdraft-blue)',
  gray: 'var(--teamupdraft-grey-400)',
  gray500: 'var(--teamupdraft-grey-500)',
  white: 'var(--teamupdraft-white)',
};

export type IconName = keyof typeof materialIcons | keyof typeof customIcons | string;
export type ColorName = keyof typeof iconColors | string;

export interface IconProps {
  name?: IconName;
  color?: ColorName;
  size?: number;
  strokeWidth?: number;
  tooltip?: TooltipProps;
  onClick?: () => void;
  className?: string;
  stroke?: string;
  fill?: string;
  type?: 'material' | 'custom';
}

const Icon = memo((
    {
    name = 'bullet',
    color = 'var(--teamupdraft-orange-dark)',
    fill = 'var(--teamupdraft-orange-dark)',
    size = 18,
    stroke = 'none',
    strokeWidth = 1.5,
    tooltip,
    onClick,
    className,
    type = "material"
    }: IconProps) => {
  // resolved color (CSS var or raw color)
  const colorVal = (iconColors[color as keyof typeof iconColors] || color) as string;

  // try to resolve components from both maps (may be undefined)
  const MaterialIcon = (materialIcons as Record<string, ComponentType<any>>)[String(name)];
  const CustomIcon = (customIcons as Record<string, ComponentType<any>>)[String(name)];

  // click handler (keyboard accessible)
  const handleClick = () => {
    if (onClick) onClick();
  };

  const handleKeyDown = (e: KeyboardEvent) => {
    if (!onClick) return;
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      onClick();
    }
  };

  // choose which icon to render based on `type` prop or availability
  let inner: ReactNode = null;

  const customElement = CustomIcon ? (
      // custom SVG/React component: pass common props (your custom icons should accept these)
      <CustomIcon width={size} height={size} stroke={stroke} strokeWidth={strokeWidth} color={colorVal} fill={fill} />
  ) : null;

  const materialElement = MaterialIcon ? (
      // MUI icons accept `sx` for size/color
      <MaterialIcon sx={{ fontSize: size, color: colorVal,"& path": {
          stroke: stroke,
          strokeWidth: strokeWidth,
          fill: fill,
        }, }} />
  ) : null;

  if (type === 'custom') {
    inner = customElement ?? materialElement;
  } else if (type === 'material') {
    inner = materialElement ?? customElement;
  } else {
    // no explicit type: prefer material if present, otherwise custom
    inner = materialElement ?? customElement;
  }

  // fallback: small filled circle (keeps layout predictable)
  if (!inner) {
    inner = <span style={{ display: 'inline-block', width: size, height: size, borderRadius: '50%', background: colorVal }} />;
  }

  const animateCss = String(name) === 'loading-circle' ? ' animate-spin' : '';
  const finalClass = `${className ?? ''} icon-${String(name)} flex items-center justify-center${animateCss}`.trim();

  const iconElement = (
      <div
          role={onClick ? 'button' : undefined}
          tabIndex={onClick ? 0 : undefined}
          onClick={handleClick}
          onKeyDown={handleKeyDown}
          className={finalClass}
          aria-hidden={onClick ? undefined : true}
      >
        {inner}
      </div>
  );

    if (tooltip) {
        return (
            <Tooltip tooltip={tooltip}>
                {iconElement}
            </Tooltip>
        );
    }

  return iconElement;
});

export default Icon;
