/**
 * Single progress model for register → address → phone/OTP → onboarding.
 * Steps 1–3 are shared; provider onboarding uses 4–7, user onboarding ends at 4.
 */
export const SIGNUP_FLOW_STEPS_PROVIDER = 7;
export const SIGNUP_FLOW_STEPS_USER = 4;

export const SIGNUP_STEP = {
    REGISTER: 1,
    ADDRESS: 2,
    PHONE_OTP: 3,
};
