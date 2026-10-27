import { Link, router } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Power, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatMoney, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    availability,
    destroy as destroyLoanProduct,
    edit as editLoanProduct,
} from '@/routes/admin/loan-products';
import { index as loansIndex } from '@/routes/admin/loans';
import type { LoanProductListItem } from '@/types';

/**
 * A loan product at a glance: its rate and method, the amount and term it
 * covers, the fees it carries and how many loans use it.
 */
export function LoanProductCard({
    loanProduct,
}: {
    loanProduct: LoanProductListItem;
}) {
    const [confirmOpen, setConfirmOpen] = useState(false);

    return (
        <>
            <Card
                className={cn(
                    'h-full gap-0 py-0',
                    !loanProduct.is_active && 'border-dashed',
                )}
            >
                <CardHeader className="gap-2 border-b p-4">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0 space-y-2">
                            <CardTitle className="truncate text-base">
                                {loanProduct.name}
                            </CardTitle>

                            <div className="flex flex-wrap items-center gap-2">
                                <Badge
                                    variant={
                                        loanProduct.is_active
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {loanProduct.is_active
                                        ? 'Active'
                                        : 'Inactive'}
                                </Badge>

                                <span className="text-xs text-muted-foreground">
                                    {loanProduct.loans_count === 0 ? (
                                        'No loans'
                                    ) : (
                                        <Link
                                            href={loansIndex({
                                                query: {
                                                    product: loanProduct.id,
                                                },
                                            })}
                                            className="hover:text-foreground hover:underline"
                                            prefetch
                                        >
                                            {loanProduct.loans_count}{' '}
                                            {loanProduct.loans_count === 1
                                                ? 'loan'
                                                : 'loans'}
                                        </Link>
                                    )}
                                </span>
                            </div>
                        </div>

                        {(loanProduct.can.update || loanProduct.can.delete) && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Actions for ${loanProduct.name}`}
                                    >
                                        <MoreHorizontal />
                                    </Button>
                                </DropdownMenuTrigger>

                                <DropdownMenuContent
                                    align="end"
                                    className="w-44"
                                >
                                    {loanProduct.can.update && (
                                        <>
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href={editLoanProduct(
                                                        loanProduct.id,
                                                    )}
                                                    prefetch
                                                >
                                                    <Pencil />
                                                    Edit
                                                </Link>
                                            </DropdownMenuItem>

                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    router.patch(
                                                        availability(
                                                            loanProduct.id,
                                                        ).url,
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Power />
                                                {loanProduct.is_active
                                                    ? 'Deactivate'
                                                    : 'Activate'}
                                            </DropdownMenuItem>
                                        </>
                                    )}

                                    {loanProduct.can.delete && (
                                        <>
                                            <DropdownMenuSeparator />

                                            <DropdownMenuItem
                                                variant="destructive"
                                                disabled={
                                                    loanProduct.loans_count > 0
                                                }
                                                onSelect={(event) => {
                                                    event.preventDefault();
                                                    setConfirmOpen(true);
                                                }}
                                            >
                                                <Trash2 />
                                                {loanProduct.loans_count > 0
                                                    ? 'Delete (in use)'
                                                    : 'Delete'}
                                            </DropdownMenuItem>
                                        </>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>

                    {loanProduct.description && (
                        <CardDescription className="line-clamp-2">
                            {loanProduct.description}
                        </CardDescription>
                    )}
                </CardHeader>

                <CardContent className="flex-1 p-4">
                    <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div className="col-span-2 flex items-baseline justify-between gap-3 border-b pb-3">
                            <dt className="text-muted-foreground">Interest</dt>

                            <dd className="text-right font-semibold">
                                {formatPercent(loanProduct.interest_rate)}

                                <span className="ml-2 text-xs font-normal text-muted-foreground">
                                    {loanProduct.interest_method_label}
                                </span>
                            </dd>
                        </div>

                        <Term
                            label="Repayment"
                            value={loanProduct.repayment_frequency_label}
                        />
                        <Term
                            label="Term"
                            value={`${loanProduct.min_term_months}–${loanProduct.max_term_months} months`}
                        />
                        <Term
                            label="Amount"
                            value={`${formatMoney(loanProduct.min_amount)} – ${formatMoney(loanProduct.max_amount)}`}
                            className="col-span-2"
                        />
                        <Term
                            label="Processing fee"
                            value={formatPercent(
                                loanProduct.processing_fee_rate,
                            )}
                        />
                        <Term
                            label="Late penalty"
                            value={
                                Number(loanProduct.penalty_rate) > 0 ? (
                                    <>
                                        {formatPercent(
                                            loanProduct.penalty_rate,
                                        )}

                                        {loanProduct.grace_period_days > 0 && (
                                            <span className="ml-1 text-xs font-normal text-muted-foreground">
                                                after{' '}
                                                {loanProduct.grace_period_days}{' '}
                                                days
                                            </span>
                                        )}
                                    </>
                                ) : (
                                    'None'
                                )
                            }
                        />
                    </dl>
                </CardContent>
            </Card>

            <ConfirmDeleteDialog
                form={destroyLoanProduct.form(loanProduct.id)}
                title={`Delete ${loanProduct.name}?`}
                description="This permanently deletes the product. Products with loans attached cannot be deleted; deactivate them instead to stop offering them."
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
            />
        </>
    );
}

function Term({
    label,
    value,
    className,
}: {
    label: string;
    value: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('space-y-0.5', className)}>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
        </div>
    );
}
