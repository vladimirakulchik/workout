export type NotificationItem = {
    id: string;
    message: string;
    url: string | null;
    read: boolean;
    created_at: string;
};

export type NotificationsProps = {
    unread_count: number;
    recent: NotificationItem[];
};
