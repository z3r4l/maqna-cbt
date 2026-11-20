export type Choice = {id:number;label:string;text:string};
export type Question = {id:number;text:string;choices:Choice[]};
export type Exam = {id:number;title:string;subject:string;duration_minutes:number;question_count:number;status:string;description?:string};
export type Attempt = {id:number;exam_id:number;remaining_seconds:number;answers:Record<string,number>;questions:Question[];submitted:boolean;score?:number};
