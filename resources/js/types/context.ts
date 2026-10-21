export type ContextOption = {
    player_id: number;
    trainer_id: number;
    player_name: string;
    trainer_name: string;
    label: string;
};

export type PlayerContext = {
    current: ContextOption | null;
    options: {
        yours: ContextOption[];
        children: ContextOption[];
    };
};
