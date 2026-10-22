import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { formatMoney } from '@/lib/format';

export type ReportBarDatum = {
    label: string;
    value: number;
    color: string;
};

/**
 * A compact horizontal bar chart for report figures. The values are also
 * rendered as screen-reader text so the chart never replaces the data table.
 */
export function ReportBarChart({
    data,
    valueFormatter = formatMoney,
    ariaLabel,
}: {
    data: ReportBarDatum[];
    valueFormatter?: (value: number) => string;
    ariaLabel: string;
}) {
    return (
        <div className="space-y-2">
            <div
                className="h-60 w-full sm:h-64"
                role="img"
                aria-label={ariaLabel}
            >
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart
                        data={data}
                        layout="vertical"
                        margin={{ top: 8, right: 24, bottom: 8, left: 8 }}
                    >
                        <CartesianGrid
                            horizontal={false}
                            stroke="var(--border)"
                            strokeDasharray="3 3"
                        />

                        <XAxis
                            type="number"
                            tickLine={false}
                            axisLine={false}
                            tick={{
                                fill: 'var(--muted-foreground)',
                                fontSize: 12,
                            }}
                            tickFormatter={(value) =>
                                valueFormatter(Number(value))
                            }
                        />

                        <YAxis
                            type="category"
                            dataKey="label"
                            width={104}
                            tickLine={false}
                            axisLine={false}
                            tick={{
                                fill: 'var(--muted-foreground)',
                                fontSize: 12,
                            }}
                        />

                        <Tooltip
                            cursor={{ fill: 'var(--muted)' }}
                            formatter={(value) => [
                                valueFormatter(Number(value)),
                                'Amount',
                            ]}
                            contentStyle={{
                                background: 'var(--popover)',
                                border: '1px solid var(--border)',
                                borderRadius: 'var(--radius)',
                                color: 'var(--popover-foreground)',
                                fontSize: '0.75rem',
                            }}
                            labelStyle={{ color: 'var(--foreground)' }}
                        />

                        <Bar
                            dataKey="value"
                            radius={[0, 4, 4, 0]}
                            maxBarSize={28}
                        >
                            {data.map((entry) => (
                                <Cell key={entry.label} fill={entry.color} />
                            ))}
                        </Bar>
                    </BarChart>
                </ResponsiveContainer>
            </div>

            <p className="sr-only">
                {ariaLabel}:{' '}
                {data
                    .map(
                        (entry) =>
                            `${entry.label} ${valueFormatter(entry.value)}`,
                    )
                    .join(', ')}
                .
            </p>
        </div>
    );
}
