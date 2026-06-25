import { APIRequestContext, expect } from '@playwright/test';

const MAILPIT_API = process.env.MAILPIT_API || 'http://127.0.0.1:8025';

interface MailpitMessage {
  ID: string;
  To: { Address: string }[];
}

/**
 * Attend l'e-mail de verification adresse a `email` (le worker Messenger le
 * traite en async) et en extrait le token du lien `/verify-email?token=...`.
 */
export async function waitForVerificationToken(
  request: APIRequestContext,
  email: string,
): Promise<string> {
  const messageId = await pollForMessage(request, email);
  const res = await request.get(`${MAILPIT_API}/api/v1/message/${messageId}`);
  expect(res.ok()).toBeTruthy();
  const body = await res.json();
  const html: string = body.HTML || body.Text || '';
  const match = html.match(/\/verify-email\?token=([^"'\s<>&]+)/);
  expect(match, `lien de verification introuvable dans l'e-mail`).not.toBeNull();
  return decodeURIComponent(match![1]);
}

async function pollForMessage(
  request: APIRequestContext,
  email: string,
  attempts = 30,
): Promise<string> {
  for (let i = 0; i < attempts; i++) {
    const res = await request.get(`${MAILPIT_API}/api/v1/messages?limit=50`);
    if (res.ok()) {
      const data = await res.json();
      const messages: MailpitMessage[] = data.messages || [];
      const found = messages.find((m) =>
        m.To?.some((t) => t.Address.toLowerCase() === email.toLowerCase()),
      );
      if (found) return found.ID;
    }
    await new Promise((r) => setTimeout(r, 500));
  }
  throw new Error(`Aucun e-mail recu pour ${email} apres ${attempts} tentatives`);
}
