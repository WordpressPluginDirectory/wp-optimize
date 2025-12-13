import { memo } from "@wordpress/element";
// @ts-ignore
import Icon from '@/utils/Icon';
const Premium = memo(({ bullets, stepId }: { bullets?: string[] | string[][], stepId: string }) => {
    if (!bullets || bullets.length === 0) return null;

    return (
        <>
            <div className="table mx-auto">
                {bullets.map((row, rowIndex) => (
                    <div key={`row-${rowIndex}`} className="table-row">
                        {row.map((bullet, colIndex) => (
                            <div key={`row-${rowIndex}-col-${colIndex}`} className="table-cell pr-3">
                                <div className="flex items-start gap-2">
                                    <Icon name="check" color="var(--teamupdraft-orange-dark)" size="25"/>
                                    <p className="text-[var(--teamupdraft-grey-700)] text-md font-normal leading-relaxed">{bullet}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                ))}
            </div>
        </>
        );
    });
export default Premium;