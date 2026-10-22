export interface Part {
  public_id: string;
  id?: string;
  name: string;
  slug: string;
  unit: "inch" | "cm";
  display_order: number;
  svg_asset_ref?: string;
  required: boolean;
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

export interface LooseAllowanceItem {
  part_name: string;
  allowance_value: string;
  notes?: string;
}

export interface Garment {
  public_id: string;
  id: string;
  name: string;
  slug: string;
  category: "gents" | "ladies" | "kids" | "unisex";
  group_name: string | null;
  base_making_minor: number;
  master_rate_minor: number;
  karigar_rate_minor: number;
  description?: string | null;
  loose_allowances?: LooseAllowanceItem[] | Record<string, string> | null;
  display_order?: number;
  illustration_url?: string | null;
  active: boolean;
  parts: Part[];
  design_options?: GarmentDesignOption[];
}
