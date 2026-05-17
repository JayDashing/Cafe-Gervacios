// Map the exported top-view model's invisible click zones to Laravel table labels.
// Only these Clickable_* objects are raycast; chairs, walls, decor, and visible
// table meshes stay visual-only.
// Edit the right-hand labels if the database table labels are renamed later.
export const TABLE_OBJECT_MAP = {
    Clickable_T1: 'T1',
    Clickable_T2: 'T2',
    Clickable_T3: 'T3',
    Clickable_T4: 'T4',
    Clickable_T5: 'T5',
    Clickable_T6: 'T10',
    Clickable_T7: 'T11',
    Clickable_T8: 'T13',
    Clickable_Booth_A1: 'T14',
    Clickable_Booth_A2: 'T15',
    Clickable_Booth_A3: 'T18',
    Clickable_Counter_1: 'T19',
    Clickable_Counter_2: 'T20',
    Clickable_Counter_3: 'T21',
};

export const TABLE_VISIBLE_OBJECT_MAP = {
    Clickable_T1: 'Table_T1',
    Clickable_T2: 'Table_T2',
    Clickable_T3: 'Table_T3',
    Clickable_T4: 'Table_T4',
    Clickable_T5: 'Table_T5',
    Clickable_T6: 'Table_T6',
    Clickable_T7: 'Table_T7',
    Clickable_T8: 'Table_T8',
    Clickable_Booth_A1: 'Booth_A1',
    Clickable_Booth_A2: 'Booth_A2',
    Clickable_Booth_A3: 'Booth_A3',
    Clickable_Counter_1: 'Counter_1',
    Clickable_Counter_2: 'Counter_2',
    Clickable_Counter_3: 'Counter_3',
};

export const TABLE_OBJECT_NAME_HINTS = [
    /^Clickable_/i,
];
