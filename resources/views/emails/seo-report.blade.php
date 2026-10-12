<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte SEO</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#111827;color:#fff;padding:20px 24px;border-radius:10px 10px 0 0;">
            <h1 style="margin:0;font-size:20px;">🎯 Oportunidades SEO</h1>
            <p style="margin:6px 0 0;font-size:14px;color:#d1d5db;">
                {{ $project->name ?? $project->domain_url }}
                @if($windowFrom && $windowTo)
                    &middot; ventana {{ $windowFrom }} → {{ $windowTo }}
                @endif
            </p>
        </div>

        <div style="background:#fff;padding:24px;border:1px solid #e5e7eb;border-top:0;border-radius:0 0 10px 10px;">
            @if($opportunities->isEmpty())
                <p style="font-size:15px;line-height:1.6;color:#374151;">
                    No se detectaron quick-wins en esta ventana. El sitio no tiene keywords
                    en posición 5–15 con margen de mejora de CTR por ahora.
                </p>
            @else
                <p style="font-size:15px;line-height:1.6;color:#374151;margin-top:0;">
                    Se detectaron <strong>{{ $opportunities->count() }}</strong> keywords en
                    posición 5–15 con CTR por debajo de su potencial. Priorizadas por clics
                    estimados que podrías ganar optimizando el título y la meta descripción:
                </p>

                @foreach($opportunities as $i => $opp)
                    <div style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin:14px 0;">
                        <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">
                            #{{ $i + 1 }} &middot; prioridad {{ $opp->priority_score }}
                        </div>
                        <div style="font-size:17px;font-weight:bold;margin:4px 0 8px;color:#111827;">
                            {{ $opp->query }}
                        </div>
                        <table style="width:100%;font-size:13px;color:#374151;border-collapse:collapse;">
                            <tr>
                                <td style="padding:2px 0;">Posición: <strong>{{ round($opp->position, 1) }}</strong></td>
                                <td style="padding:2px 0;">Impresiones: <strong>{{ $opp->impressions }}</strong></td>
                                <td style="padding:2px 0;">CTR: <strong>{{ round($opp->ctr * 100, 1) }}%</strong></td>
                                <td style="padding:2px 0;">Clics pot.: <strong style="color:#059669;">+{{ $opp->potential_clicks }}</strong></td>
                            </tr>
                        </table>
                        <div style="font-size:12px;color:#6b7280;margin-top:6px;word-break:break-all;">
                            {{ $opp->page }}
                        </div>

                        @if(!empty($opp->ai_suggestions))
                            <div style="background:#f9fafb;border-radius:6px;padding:12px;margin-top:12px;">
                                <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">
                                    🤖 Sugerencia de la IA
                                </div>
                                <div style="font-size:13px;color:#111827;margin-bottom:6px;">
                                    <strong>Title:</strong> {{ $opp->ai_suggestions['title'] ?? '' }}
                                </div>
                                <div style="font-size:13px;color:#374151;">
                                    <strong>Meta:</strong> {{ $opp->ai_suggestions['meta_description'] ?? '' }}
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif

            <p style="font-size:12px;color:#9ca3af;margin-top:24px;border-top:1px solid #e5e7eb;padding-top:16px;">
                Reporte generado automáticamente por Guru SEO Monitor a partir de datos de
                Google Search Console.
            </p>
        </div>
    </div>
</body>
</html>
