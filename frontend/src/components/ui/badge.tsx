import * as React from 'react';import {cn} from '@/lib/utils';
export function Badge({className,...props}:React.HTMLAttributes<HTMLSpanElement>){return <span className={cn('inline-flex rounded-full bg-[#E5F3EF] px-2.5 py-1 text-xs font-medium text-[#155451]',className)} {...props}/>}
