import TextInput from "./TextInput";
import FieldWrapper from "./FieldWrapper";
import Icon from "../../utils/Icon";
import useOnboardingStore from "../../store/useOnboardingStore";
import {useEffect, useState} from "@wordpress/element";
import {handleRequest} from '../../utils/api.js';

const TwoFaValidation = ({
	field,
	onChange,
	fieldStatus,
	value
}) => {
    const {
        onboardingData,
    } = useOnboardingStore();
    const [key, setKey] = useState(value || "");
    const [keyValid, setKeyValid] = useState(false);

    const handleChange = (newValue) => {
        setKey(newValue);
        onChange(newValue);
    }

    /**
     * Check if the provided key is valid by making an API request.
     * @param key
     */
    const keyIsValid = async (key) => {
        if (key.length != 6) {
            return false;
        }

        const path = onboardingData.prefix + '/v1/onboarding/tfa_key_is_valid';
        const method = 'GET';

        const args = {
            path,
            method,
            data: { key: key, nonce: onboardingData.nonce },
        };
        const response = await handleRequest(args);
        return response.success;
    };

    useEffect(() => {
        if (!key) {
            setKeyValid(false);
            fieldStatus(false);
            return;
        }

        const delay = setTimeout(async () => {
            const isValid = await keyIsValid(key);
            setKeyValid(isValid);
            fieldStatus(isValid);
        }, 300);

        return () => clearTimeout(delay);
    }, [key]);

    return (
        <div className={" w-full"} >
            <FieldWrapper inputId={field.id} label={field.label}>
                <div className={"relative w-full"}>
                    <TextInput placeholder={field.placeholder} type="text"
                           onChange={(e) => handleChange(e.target.value)} value={value}/>
                    <div className="absolute right-4 top-1/2 -translate-y-1/2">
                        {
                            keyValid ? (
                                <Icon name="check" color="green"/>
                            ) : (
                                <Icon name="times" color="red"/>
                            )
                        }
                    </div>
                </div>
            </FieldWrapper>
        </div>
    )
};

export default TwoFaValidation;