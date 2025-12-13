import License from './License';
import Checkbox from './Checkbox';
import TrackingTest from './TrackingTest';
import Email from './Email';
import Password from './Password';
import Plugins from './Plugins';
import QrCode from './QrCode';
import TwoFaValidation from './TwoFaValidation';
import BackupCodes from './BackupCodes';
import Dropdown from './Dropdown';
import NumberInputWithControls from './NumberInputWithControls';
import MultiSelectDropdown from './MultiSelectDropdown';
import { ErrorBoundary } from '../ErrorBoundary';
// @ts-ignore
import useOnboardingStore from "@/store/useOnboardingStore";

type FieldsProps = {
    fields: any[];
    onChange: (id: string, value: any) => void;
    fieldStatus?: (id: string, success: boolean) => void;
};
/**
 * Fields component that renders different field types based on the field configuration
 * @param {Object} props Component props
 * @param {Array} props.fields Array of field configurations
 * @param {Function} props.onChange Callback function when field values change
 * @param {Function} props.fieldStatus Callback function when success status of field changes
 * @returns {JSX.Element|null} The rendered fields or null if no fields
 */
const Fields = ({ fields, onChange, fieldStatus = () => {} }:FieldsProps ) => {
    const {
        getValue,
        setValue,
        isEdited,
    } = useOnboardingStore();
    if (!fields) return null;

    const fieldComponents = {
        two_fa_validation: TwoFaValidation,
        qr_code: QrCode,
        backup_codes: BackupCodes,
        license: License,
        checkbox: Checkbox,
        tracking_test: TrackingTest,
        email: Email,
        plugins: Plugins,
        password: Password,
        dropdown: Dropdown,
        number: NumberInputWithControls,
        multi_select: MultiSelectDropdown,
    };

    //the settings contain the values.
    return (
        <ErrorBoundary>
            {fields.map((field) => {
                let value = getValue(field.id);
                const isEditedField = isEdited(field.id);
                if (!isEditedField && (value === undefined || value === null) && field.default !== undefined) {
                    const disabled = field.is_lock === true;

                    if (disabled) {
                        setValue(field.id, false);
                        value = false;
                    } else {
                        setValue(field.id, field.default);
                        value = field.default;
                    }
                }
                const Component = fieldComponents[field.type] || null;
                return Component
                    ? <Component key={field.id} field={field} onChange={(value) => onChange(field.id, value)} fieldStatus={(success) => fieldStatus(field.id, success)} value={value} />
                    : null;
            })}
        </ErrorBoundary>
    );
};

export default Fields;