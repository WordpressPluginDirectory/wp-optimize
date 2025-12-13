import {useEffect, memo, useMemo, useState, useRef} from "@wordpress/element";
import Modal from "./Modal/Modal";
import { ErrorBoundary } from './ErrorBoundary';
import useOnboardingStore from "../store/useOnboardingStore";
import { ModalTopBar } from "./Modal/ModalTopBar";
import { ModalHeader } from "./Modal/ModalHeader";
import { ModalContent } from "./Modal/ModalContent";
import { ModalFooter } from "./Modal/ModalFooter";
import { __ } from '@wordpress/i18n';
import {TooltipContainerProvider} from "../utils/Tooltip/TooltipContainerContext";
import { isValidEmail } from '../utils/validators';
import Icon from '../utils/Icon';
import {updateAction} from "../utils/api";

/**
 * Onboarding component that guides users through a series of steps
 * @returns {JSX.Element} The Onboarding component
 */
const Onboarding = () => {
    const [ConfettiExplosion, setConfettiExplosion] = useState(null);
    const [isExploding, setIsExploding] = useState(false);

    const {
        isOpen,
        currentStepIndex,
        steps,
        setOpen,
        setCurrentStepIndex,
        addSuccessStep,
        setSteps,
        onboardingData,
        setResponseMessage,
        setResponseSuccess,
        responseMessage,
        responseSuccess,
        responseCode,
        updateEmail,
        updateStepSettings,
        installPlugins,
        validateLicense,
        licenseStatus,
        settings,
        setValue,
        getSettings,
        isLastStep,
        trackingTestRunning,
        isInstalling,
        continueDisabled,
        setContinueDisabled,
        isUpdating,
    } = useOnboardingStore();

    useEffect(() => {
        if ( !!isLastStep() ) {
            import ( "react-confetti-explosion").then(
                ({default: ConfettiExplosion}) => {
                    setConfettiExplosion(() => ConfettiExplosion);
                });
            setIsExploding(true);
        }
    }, [isLastStep()]);

    // Memoize the settings and visible steps to prevent unnecessary recalculations
    const visibleSteps = useMemo(() => onboardingData.steps?.filter(step => step.visible !== false) || [], [onboardingData.steps]);
    const currentStep = visibleSteps[currentStepIndex];
    const tooltipContainerRef = useRef<HTMLDivElement>(null);

    // Initialize steps only once when the component mounts
    useEffect(() => {

        if (visibleSteps.length > 0 && steps.length === 0) {
            setSteps(visibleSteps);
        }
    }, [visibleSteps, steps.length, setSteps]);

    useEffect(() => {
        if ( settings.length === 0 ) {
            getSettings();
        }
    }, [settings]);

    // Clear any prior response message when the step changes (screen change)
    useEffect(() => {
        setResponseMessage('');
        setResponseSuccess(true);
    }, [currentStepIndex]);


    const handleClose = () => {
        setOpen(false);
        updateAction({}, 'user_skipped_wizard');
    };

    const handlePrevious = () => {
        setCurrentStepIndex(currentStepIndex - 1);
        setContinueDisabled(false);
    };

    const validateAndContinue = async (e) => {
        // Always clear previous response state upon a new submission attempt
        setResponseMessage('');
        //setResponseSuccess(true);

        let success = true;
        if ( currentStep.type === 'license' && licenseStatus !== 'activated') {
            await validateLicense();
            // Do not auto-advance for license; show the result view with the message
            return;
        }

        //save the current values
        if ( currentStep.type === 'settings' ) {
            await updateStepSettings(settings);
        }

        if ( currentStep.type === 'email' ) {
            await updateEmail();
        }

        if (currentStep.type === 'plugins'){
            const allInstalled = onboardingData.is_all_plugins_installed;
            if(!allInstalled) {
                //we don't wait for the plugins to be installed, so the user can continue.
                //Make this call async after the render, e.g., in a useEffect or setTimeout:
                setTimeout(() => {
                    installPlugins();
                }, 0);
            }
        }

        if ( !success ) {
            return;
        }
        await handleContinue(e);
    }

    const changeFieldValue = async (fieldId: string, value: string | boolean) => {
        setValue(fieldId, value);
    };

    // Function to determine if the continue button should be disabled.
    const isContinueDisabled = () => {
        if (continueDisabled) {
            return true;
        }

        // For tracking test step, only disable continue button while test is running.
        if (currentStep?.id === 'tracking') {
            return trackingTestRunning;
        }

        // we don't want to close the modal during installation. Otherwise it might not complete.
        if ( isLastStep() && isInstalling ) {
            return true;
        }

        // Disable when on an email-related step only if an email field exists, and it's empty or invalid.
        if (currentStep?.type === 'email' || currentStep?.type === 'license') {
            const emailField = currentStep.fields?.find((f: any) => f?.type === 'email');

            // If no email field is present, do not disable.
            if (!emailField) {
                // proceed with other checks below
            } else {
                // Support both object-map and array-shaped settings
                const rawValue =
                    (settings && typeof settings === 'object' && !Array.isArray(settings) ? settings[emailField.id] : undefined) ??
                    (Array.isArray(settings) ? settings.find((s: any) => s?.id === emailField.id)?.value : undefined) ??
                    '';

                if (!isValidEmail(rawValue)) {
                    return true;
                }
            }

            // Checkbox constraint (apply if a checkbox field exists)
            const checkboxField = currentStep.fields?.find((f: any) => f?.type === 'checkbox');
            if (checkboxField) {
                const checkboxValue =
                    (settings && typeof settings === 'object' && !Array.isArray(settings) ? settings[checkboxField.id] : undefined) ??
                    (Array.isArray(settings) ? settings.find((s: any) => s?.id === checkboxField.id)?.value : undefined) ??
                    false;

                if (checkboxValue !== true) {
                    return true;
                }
            }
        }
        return false;
    };

    const handleContinue = async (e) => {
        // make sure the response message is cleared
        setResponseMessage('');
        setResponseSuccess(true);
        if (settings && currentStep.fields) {
            const atLeastOneFieldIsTrue = currentStep.fields.some((field: { id: string }) => {
                const setting = settings.find(item => item.id === field.id);
                const value = setting ? setting.value : false;
                return value === true || (typeof value === 'string' && value.trim() !== '');
            });
            if (atLeastOneFieldIsTrue) {
                addSuccessStep(currentStep.id);
            }
        }

        setCurrentStepIndex(currentStepIndex + 1);
        setContinueDisabled(false);
        // If this is the last step, reload the page if this is so configured.
        if (currentStepIndex + 1 >= steps.length && onboardingData.reload_on_finish ) {
            window.location.reload();
        }
    };

    //open the modal when the component mounts
    useEffect(() => {
        setOpen(true);
    }, []);

    if (!currentStep) {
        return null;
    }

    return (
        <ErrorBoundary>
            <div id="onboarding-modal-root"></div>

            <Modal
                logo={onboardingData.logo}
                logo_class={`${onboardingData.prefix}-logo`}
                title={__('Onboarding', 'ONBOARDING_WIZARD_TEXT_DOMAIN')}
                currentStepIndex={currentStepIndex}
                content={
                    <div className="flex flex-col gap-2 mb-3">
                        <TooltipContainerProvider container={tooltipContainerRef.current}>
                            <div ref={tooltipContainerRef} className="flex flex-col gap-2 justify-center items-stretch">

                                <ModalTopBar
                                    currentStep={currentStep}
                                    handlePrevious={handlePrevious}
                                />

                                <ModalHeader
                                    currentStep={currentStep}
                                />

                                {
                                    // Hide ModalContent
                                    // 1. When a license is activated or showing license success result
                                    // 2. When all plugins are already installed
                                    !((currentStep.type === 'license' && (licenseStatus === 'activated' || isUpdating)) || (currentStep.type === 'plugins' && onboardingData.is_all_plugins_installed)
                                    ) && (
                                        <ModalContent
                                            step={currentStep}
                                            settings={settings}
                                            onFieldChange={changeFieldValue}
                                        />
                                    )
                                }
                                {responseCode !== 'BADAUTHPWD' && responseMessage && (
                                    <div
                                        className={`flex items-start gap-2 rounded-md border px-4 py-3 text-sm ${
                                            responseSuccess
                                                ? "border-[#046C4E] bg-[#046c4e4f]"
                                                : "border-[#FECACA] bg-[#FEF2F2] "
                                        }`}
                                    >
                                        {/* Icon */}
                                        <Icon
                                            name={responseSuccess ? 'success' : 'info'}
                                            color={responseSuccess ? '#046C4E' : '#B40000'}
                                            fill={responseSuccess ? '#046C4E' : '#B40000'}
                                            size={16}
                                            className="mr-2 mt-[3px]"
                                        />

                                        {/* Message */}
                                        <div>
                                            <p className="font-semibold text-[#1C252C]">
                                                {responseSuccess ? "Success" : "Error"}
                                            </p>
                                            <p className="text-[#4F565B]">{responseMessage}</p>
                                        </div>
                                    </div>
                                )}


                                <ModalFooter
                                    isContinueDisabled={isContinueDisabled}
                                    validateAndContinue={validateAndContinue}
                                    currentStep={currentStep}
                                    handleContinue={handleContinue}
                                />

                            </div>
                        </TooltipContainerProvider>
                    </div>
                }
                isOpen={isOpen}
                onClose={handleClose}
                footer={null}
                triggerClassName=""
                children={null}
            />
            {isExploding && ConfettiExplosion && <div className="absolute top-1/4 left-1/2 -translate-x-1/2"><ConfettiExplosion duration={4000} width={1400} particleCount={200} force={0.7} zIndex={999999}/></div>}
        </ErrorBoundary>
    );
};

export default memo(Onboarding);