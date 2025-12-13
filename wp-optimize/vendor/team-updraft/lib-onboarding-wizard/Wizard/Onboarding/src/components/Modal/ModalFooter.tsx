import ButtonInput from "../Inputs/ButtonInput";
// @ts-ignore
import Icon from '@/utils/Icon';
import { __ } from "@wordpress/i18n";
import useOnboardingStore from "../../store/useOnboardingStore";
import {get_website_url} from "../../utils/lib.js";

export const ModalFooter = ({
                                isContinueDisabled,
                                validateAndContinue,
                                currentStep,
                                handleContinue,
                            }) => {
    const {
        onboardingData,
        isUpdating,
        currentStepIndex,
        footerMessage,
        isLastStep,
        isInstalling,
        licenseStatus,
    } = useOnboardingStore();

    const upgradeUrl = get_website_url(onboardingData.upgrade, {
        utm_source:onboardingData.prefix + '_onboarding',
        utm_content: 'upgrade'
    });

    // Decide disabled state once to reuse below
    const continueDisabled = isContinueDisabled();
    const isLicenseAndUpdating = currentStep?.type === 'license' && licenseStatus === 'activating'
    const isLicenseAndValid = currentStep?.type === 'license' && licenseStatus === 'activated'

    const allInstalled = onboardingData.is_all_plugins_installed;
    const isPluginStepAndAllInstalled = currentStep?.type === 'plugins' && allInstalled

    return (<>
            {currentStep.enable_premium_btn === true && !onboardingData.is_pro && (
                <div className="flex flex-row gap-4 justify-center items-center min-w-[32ch]">
                <ButtonInput
                    className="flex justify-center items-center outline-none px-2 py-[5px] relative w-full"
                    btnVariant="secondary"
                    size="lg"
                    link={upgradeUrl}
                >
                    {currentStep.premium_btn_text ? currentStep.premium_btn_text :  __('Go Premium', 'ONBOARDING_WIZARD_TEXT_DOMAIN')}

                    <Icon
                        name="magic-wand"
                        size={24}
                        color="white"
                        fill="white"
                        type=""
                        className="ml-2"
                    />
                </ButtonInput>
                </div>
            )}

            <div className="flex flex-col md:flex-row gap-4 justify-center items-center min-w-[32ch] pt-2">
                {(currentStepIndex > 0 && !isLastStep() && !isLicenseAndUpdating && !isLicenseAndValid && !isPluginStepAndAllInstalled) && (
                    <div className="flex flex-row justify-center items-center w-full order-last md:order-first">
                        <ButtonInput
                            className="burst-skip !text-[#C4511C] !font-semibold hover:!text-orange-darkish hover:underline hover:decoration-2 hover:underline-offset-4"
                            btnVariant="transparent"
                            size="sm"
                            onClick={(e) => handleContinue(e)}
                        >
                            {__('Skip this step', 'ONBOARDING_WIZARD_TEXT_DOMAIN')}
                        </ButtonInput>
                        {/* Skip Step Button Icon - Only render if the icon is provided */}
                        {currentStep.skip_step && (
                            <Icon
                                name={currentStep.skip_step?.icon ?? 'info'}
                                color="#C4511C"
                                fill="#C4511C"
                                size={16}
                                tooltip={
                                    currentStep.skip_step?.tooltip
                                        ? {
                                            ...currentStep.skip_step.tooltip,
                                            side: "bottom",
                                            align: "center",
                                        }
                                        : ''
                                }
                                className="ml-2"
                            />
                        )}
                    </div>
                )}

                {!isLicenseAndUpdating && currentStep.button && (
                <ButtonInput
                    className="burst-continue flex justify-center items-center outline-none px-2 py-[5px] relative w-full"
                    btnVariant={continueDisabled ? "transparent-disabled" : "secondary"}
                    size="lg"
                    disabled={continueDisabled}
                    onClick={(e) => validateAndContinue(e)}
                    key={currentStep.id + "continue"}
                >
                    <Icon
                        name="loading-circle"
                        size={18}
                        fill="white"
                        color={isLastStep() ? "black" : "white"}
                        className={`absolute left-3 transition-opacity duration-300 ${isUpdating || isInstalling ? "opacity-100" : "opacity-0"}`}
                    />
                    <span className="flex mx-6 font-medium whitespace-nowrap truncate">
                        {/* Button label */}
                        {isLicenseAndValid ? __('Continue', 'ONBOARDING_WIZARD_TEXT_DOMAIN') : currentStep.button.label}

                        {/* Button Icon - Only render if icon is provided */}
                        {currentStep.button.icon && (
                            <Icon
                                name={currentStep.button.icon}
                                size={currentStepIndex === 0 ? 26 : 18}
                                color={isLastStep() ? "black" : "white"}
                                fill={isLastStep() ? "black" : "white"}
                                type=""
                                className="ml-2"
                            />
                        )}
                    </span>
                </ButtonInput>
                )}

            </div>
            {currentStep.note && (
                <div className="text-center">
                    <p className="whitespace-break-spaces font-medium text-sm text-[var(--teamupdraft-grey-600)]">{currentStep.note}</p>
                </div>
            )}
            {footerMessage && (
                <div className="flex justify-center gap-2 text-sm text-[var(--teamupdraft-grey-600)] text-center rounded-md border border-[var(--teamupdraft-orange-dark)] p-2 mt-2">
                    <Icon
                        name='loading-circle'
                        color='var(--teamupdraft-orange-dark)'
                        fill='var(--teamupdraft-orange-dark)'
                        size={18}
                        className="mr-2"
                    />
                    <div>
                        {footerMessage}
                    </div>
                </div>
            )}
        </>
    );
}