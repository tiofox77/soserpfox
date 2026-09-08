import { api } from './cliente';

export type Canal = 'email' | 'sms' | 'whatsapp';
export type MapaBooleano = Record<string, boolean>;
export type MapaTemplates = Record<string, number | string | null>;

export type DefinicoesDeGateways = {
    email_enabled: boolean; smtp_host: string | null; smtp_port: number | null; smtp_username: string | null;
    smtp_password?: string; smtp_encryption: 'tls' | 'ssl' | null; from_email: string | null; from_name: string | null;
    sms_enabled: boolean; sms_provider: string | null; sms_account_sid: string | null; sms_auth_token?: string;
    sms_from_number: string | null; sms_api_token?: string; sms_sender_id: string | null;
    whatsapp_enabled: boolean; whatsapp_provider: string | null; whatsapp_account_sid: string | null;
    whatsapp_auth_token?: string; whatsapp_from_number: string | null; whatsapp_business_account_id: string | null;
    whatsapp_sandbox: boolean;
    email_notifications: MapaBooleano; sms_notifications: MapaBooleano; whatsapp_notifications: MapaBooleano;
    email_notification_templates: MapaTemplates; sms_notification_templates: MapaTemplates; whatsapp_notification_templates: MapaTemplates;
    whatsapp_templates: Array<Record<string, unknown>>;
};

export type EcraDeGateways = {
    definicoes: DefinicoesDeGateways;
    segredos_guardados: Record<string, boolean>;
    templates: Array<{ id: number; name: string; module: string; email_enabled: boolean; sms_enabled: boolean; whatsapp_enabled: boolean }>;
    eventos: Array<{ chave: string; nome: string; icone: string }>;
    permissoes: { pode_editar: boolean };
};

export const gatewaysDeNotificacao = {
    ler: () => api.ler<EcraDeGateways>('/notification-gateways'),
    guardar: (corpo: DefinicoesDeGateways) => api.guardar<{ message: string; segredos_guardados: Record<string, boolean> }>('/notification-gateways', corpo),
    testarSms: (corpo: Pick<DefinicoesDeGateways, 'sms_provider' | 'sms_api_token' | 'sms_sender_id'>) =>
        api.criar<{ success: boolean; message: string; balance?: number | null; currency?: string }>('/notification-gateways/testar-sms', corpo),
    testarEmail: (corpo: Partial<DefinicoesDeGateways>) => api.criar<{ success: boolean; message: string }>('/notification-gateways/testar-email', corpo),
    templatesWhatsApp: (corpo: Partial<DefinicoesDeGateways>) => api.criar<{ templates: Array<{ sid: string; name: string; status?: string }>; message: string }>('/notification-gateways/whatsapp/templates', corpo),
    testarWhatsApp: (corpo: Partial<DefinicoesDeGateways> & { test_phone: string; template_sid: string; template_name: string; variables: Record<string, string> }) =>
        api.criar<{ success: boolean; message: string; sid: string }>('/notification-gateways/whatsapp/testar', corpo),
};
