export type SequenceAiDraft = {
    name: string;
    steps: {
        delay_days: number;
        subject: string;
        body: string;
    }[];
};
