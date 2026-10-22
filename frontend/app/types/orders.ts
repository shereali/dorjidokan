export interface Part {
  id: string;
  public_id?: string;
  name: string;
  slug?: string;
  unit: string;
  display_order: number;
  required?: boolean;
  svg_asset_ref?: string;
}

export interface Measurement {
  part_id: string;
  value: number;
  unit: string;
}

export interface Customer {
  id?: string;
  public_id?: string;
  name: string;
  mobile_number?: string;
}

export interface GarmentDesignValue {
  id?: string;
  public_id?: string;
  name: string;
  extra_price_minor: number;
  is_default: boolean;
  display_order: number;
}

export interface GarmentDesignOption {
  id?: string;
  public_id?: string;
  name: string;
  type: "select" | "checkbox" | "radio";
  display_order: number;
  values: GarmentDesignValue[];
}

export interface Garment {
  id?: string;
  public_id?: string;
  name: string;
  slug?: string;
  category?: "gents" | "ladies" | "kids" | "unisex";
  group_name?: string;
  base_making_minor?: number;
  master_rate_minor?: number;
  karigar_rate_minor?: number;
  active?: boolean;
  parts?: Part[];
  design_options?: GarmentDesignOption[];
}

export interface Employee {
  id: string;
  name: string;
  active: boolean;
  employee_type: string;
}

export interface Timeline {
  status: string;
  from_status?: string;
  note?: string;
  created_at: string;
}

export interface Order {
  id: string;
  number?: string;
  order_number?: string;
  barcode?: string;
  status: string;
  customer: { name: string; mobile_number?: string; public_id?: string };
  garment: { name: string; parts?: Part[] };
  karigar?: { id: string; name: string };
  measurements?: Measurement[];
  timeline?: Timeline[];
  items?: Array<{ id: string; garment: { name: string }; quantity: number; making_cost_minor: number; design_cost_minor: number; group_name?: string }>;
  total_minor: number;
  paid_minor: number;
  promised_at?: string;
  archived_at?: string;
}
