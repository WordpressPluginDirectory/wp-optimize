import * as RadixAccordion from '@radix-ui/react-accordion';
import Icon from "../../utils/Icon";
import useOnboardingStore from "../../store/useOnboardingStore";
import Fields from "./Fields";
import ButtonInput from "../Inputs/ButtonInput";
import { memo, useEffect, useState } from "@wordpress/element";
import { __ } from '@wordpress/i18n';

const Accordion = ({
    fields,
    groups,
    onChange,
}) => {
    const {
        currentStepIndex,
        setContinueDisabled,
    } = useOnboardingStore();

    const [currentOpen, setCurrentOpen] = useState<number>(0);
    const [closed, setClosed] = useState<Set<number>>(new Set([]));
    const [failed, setFailed] = useState<Record<number, Set<string>>>({});

    const fieldStatus = (index: number, fieldID: string, success: boolean) => {
        setFailed(prevFailed => {
            const newFailedIDs = new Set(prevFailed[index] ?? []);

            if (success) {
                newFailedIDs.delete(fieldID);
            } else {
                newFailedIDs.add(fieldID);
            }

            if (newFailedIDs.size === 0) {
                const {[index]: _, ...rest} = prevFailed;
                return rest;
            } else {
                return {...prevFailed, [index]: newFailedIDs};
            }
        });
    }

    const accordionChange = (value: number | string) => {
        const valueID = value === '' ? -1 : Number(value);

        setClosed(prevClosed => {
            const outOfRange = currentOpen < 0 || currentOpen >= groups.length;

            if (!outOfRange && !prevClosed.has(currentOpen) && !(currentOpen in failed)) {
                return new Set(prevClosed).add(currentOpen);
            }

            return prevClosed;
        });

        setCurrentOpen(valueID);
    }

    useEffect(() => {
        setContinueDisabled(closed.size < groups.length);
    }, [closed, currentStepIndex]);

    return (
        <RadixAccordion.Root
            type="single"
            value={`${currentOpen}`}
            onValueChange={accordionChange}
            collapsible
        >
            {groups.map((group, index) => (
                <RadixAccordion.Item key={index} className="rounded-xl border border-grey data-[state=open]:border-orange-darkish overflow-hidden my-2" value={`${index}`}>
                    <RadixAccordion.Header className="flex">
                        <RadixAccordion.Trigger className="group font-semibold text-md px-3.5 h-[45px] flex-1 flex items-center justify-between bg-white hover:data-[state=closed]:bg-blue-lightest">
                            {group.title}
                            {closed.has(index)
                                ? <Icon
                                    name="success"
                                    size={24}
                                    strokeWidth={1}
                                    stroke="none"
                                    color="#15803D"
                                    fill="#15803D"
                                />
                                : <Icon
                                    name="expand"
                                    size={24}
                                    strokeWidth={1}
                                    stroke="none"
                                    color="gray-500"
                                    fill="gray-500"
                                />
                            }
                        </RadixAccordion.Trigger>
                    </RadixAccordion.Header>
                    <RadixAccordion.Content className="data-[state=open]:animate-slideDown data-[state=closed]:animate-slideUp">
                        <div className="mx-3.5 space-y-1">
                            <Fields
                                fields={fields.filter(item => item.group_id === group.id)}
                                onChange={onChange}
                                fieldStatus={(fieldID, success) => fieldStatus(index, fieldID, success)}
                            />
                        </div>
                        <div className="flex flex-row gap-4 justify-center items-center min-w-[32ch]">
                            <ButtonInput
                                className="w-full burst-continue flex justify-center items-center outline-none px-2 m-3.5"
                                btnVariant="secondary"
                                size="md"
                                onClick={(_) => accordionChange(index+1)}
                                disabled={currentOpen in failed}
                                key={index + "continue"}
                            >
                                {__('Confirm', 'ONBOARDING_WIZARD_TEXT_DOMAIN')}
                            </ButtonInput>
                        </div>
                    </RadixAccordion.Content>
                </RadixAccordion.Item>
            ))}
        </RadixAccordion.Root>
    )
};

export default memo(Accordion);
