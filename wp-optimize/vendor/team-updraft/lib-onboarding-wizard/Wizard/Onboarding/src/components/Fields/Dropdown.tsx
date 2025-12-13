import { memo } from 'react';
import FieldWrapper from './FieldWrapper';
import { __ } from '@wordpress/i18n';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from './DropdownInput';
import Icon from '../../utils/Icon';

interface DropdownOption {
    value: string;
    label: string;
}

interface DropdownFieldProps {
    field: {
        id: string;
        label?: string;
        options: DropdownOption[];
        is_lock?: boolean;
        tooltip?: any;
        [key: string]: any;
    };
    value: string;
    onChange: (value: string) => void;
}

const DropdownInput = ({ field, value, onChange } :DropdownFieldProps) => {
    return (
        <FieldWrapper
            inputId={field.id}
            label={
                <div className="flex items-center gap-2">
                    <span>{field.label}</span>
                    {field.tooltip && (
                        <Icon
                            name={field.tooltip.icon ?? 'info'}
                            color="gray500"
                            fill="gray500"
                            size={16}
                            tooltip={field.tooltip}
                            className="ml-[-4px]"
                        />
                    )}
                </div>
            }
        >
            <Select onValueChange={onChange} value={value} disabled={field.is_lock}>
                <SelectTrigger className="w-full">
                    <SelectValue placeholder={__("Select an option", "ONBOARDING_WIZARD_TEXT_DOMAIN")} />
                </SelectTrigger>
                <SelectContent>
                    {field.options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </FieldWrapper>
    );
};

export default memo(DropdownInput);