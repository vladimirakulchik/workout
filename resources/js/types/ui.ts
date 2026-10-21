export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type AvailabilityRange = {
    day_of_week: number;
    start_time: string;
    end_time: string;
};
