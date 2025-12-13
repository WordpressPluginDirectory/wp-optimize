import type {SettingField, Step} from "../../types";
import {memo} from "@wordpress/element";
import Premium from "./Premium";
import Fields from "../Fields/Fields";
// @ts-ignore
import Icon from '@/utils/Icon';
import Accordion from "../Fields/Accordion";

export const ModalContent = memo(({ step, settings, onFieldChange }: {
    step: Step,
    settings: SettingField[],
    onFieldChange: (fieldId: string, value: string | boolean) => void
}) => {

    return (
    <div className="w-full max-w-[70ch] mx-auto flex flex-col my-3 space-y-2 gap-2">
        {step.intro_bullets?.length > 0 && (
            <div className="space-y-2 flex flex-col ">
                {step.intro_bullets.map((bullet, index) => (
                    <div key={`${step.id}-bullet-${index}`} className="flex items-center gap-2 ml-2">
                        <span className="flex-shrink-0">
                          <Icon
                              name={bullet.icon}
                              size={24}
                              type=""
                              strokeWidth={1}
                              stroke="none"
                              color="var(--teamupdraft-orange-dark)"
                              className="bg-[#fff5eb] rounded-[80px] p-2 shadow-[0_0_10px_#fff5eb] border border-[#fff5eb]"
                          />
                        </span>
                        <div>
                            <p className="font-medium text-md">{bullet.title}</p>
                            <p className="text-[var(--teamupdraft-grey-600)] text-md">{bullet.desc}</p>
                        </div>
                    </div>
                ))}
            </div>
        )}
        {step.bullets?.length > 0 &&
            <Premium bullets={step.bullets} stepId={step.id} />
        }
        { /*Grouped field*/ }
        { step?.groups?.length > 0 && (
            <Accordion
                key={step.id}
                groups={step.groups}
                fields={step.fields}
                onChange={onFieldChange}
            />
        )}

        {/* Non-grouped field, which are all fields without a group_id. */ }
        { step?.fields?.some(f => !f.group_id) && (
            <Fields
                fields={step.fields.filter(f => !f.group_id)}
                onChange={onFieldChange}
            />
        )}
    </div>
    );
});
