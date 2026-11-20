import * as React from 'react';
import { CheckIcon, ChevronDownIcon } from 'lucide-react';
import { Select as SelectPrimitive } from 'radix-ui';
import { cn } from 'cn';

function Select(props: React.ComponentProps<typeof SelectPrimitive.Root>) {
  return <SelectPrimitive.Root data-slot="select" {...props} />;
}

function SelectTrigger({ className, children, ...props }: React.ComponentProps<typeof SelectPrimitive.Trigger>) {
  return <SelectPrimitive.Trigger data-slot="select-trigger" className={cn('flex min-h-11 items-center justify-between gap-2 rounded-md border border-input bg-white px-3 text-sm text-foreground outline-none focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50', className)} {...props}>{children}<SelectPrimitive.Icon asChild><ChevronDownIcon className="size-4 text-muted-foreground" /></SelectPrimitive.Icon></SelectPrimitive.Trigger>;
}

function SelectValue(props: React.ComponentProps<typeof SelectPrimitive.Value>) {
  return <SelectPrimitive.Value data-slot="select-value" {...props} />;
}

function SelectContent({ className, children, ...props }: React.ComponentProps<typeof SelectPrimitive.Content>) {
  return <SelectPrimitive.Portal><SelectPrimitive.Content data-slot="select-content" position="popper" className={cn('z-50 min-w-[var(--radix-select-trigger-width)] overflow-hidden rounded-md border border-border bg-white p-1 text-foreground shadow-md', className)} {...props}><SelectPrimitive.Viewport>{children}</SelectPrimitive.Viewport></SelectPrimitive.Content></SelectPrimitive.Portal>;
}

function SelectItem({ className, children, ...props }: React.ComponentProps<typeof SelectPrimitive.Item>) {
  return <SelectPrimitive.Item data-slot="select-item" className={cn('relative flex min-h-10 cursor-pointer items-center rounded-sm py-2 pr-8 pl-3 text-sm outline-none focus:bg-muted focus:text-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50', className)} {...props}><SelectPrimitive.ItemText>{children}</SelectPrimitive.ItemText><SelectPrimitive.ItemIndicator className="absolute right-2"><CheckIcon className="size-4" /></SelectPrimitive.ItemIndicator></SelectPrimitive.Item>;
}

export { Select, SelectTrigger, SelectValue, SelectContent, SelectItem };
