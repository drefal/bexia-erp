# Bexia ERP - Cierre final módulo RRHH y Nómina

## Versión

V5.64.29 - Cierre final RRHH/Nómina.

## Alcance validado

Este cierre documenta el avance funcional del módulo de Recursos Humanos y Nómina construido sobre Bexia ERP.

Flujo integral cubierto:

1. Catálogos base de RRHH/Nómina.
2. Ficha de empleado mejorada.
3. Expediente documental del empleado.
4. Incidencias de empleados.
5. Aprobaciones de incidencias.
6. Vacaciones y saldos.
7. Solicitud de vacaciones desde empleado.
8. Contratos laborales.
9. Bajas laborales.
10. Organigrama básico.
11. Horarios operativos.
12. Asistencia y checador.
13. Generación de incidencias desde asistencia.
14. Reporte de asistencia.
15. Pre-nómina base.
16. Exportación y control de pre-nómina.
17. Recibo interno de nómina.
18. Portal empleado: mis recibos.
19. Políticas de nómina configurables.
20. Conceptos de nómina configurables.
21. Préstamos, anticipos y descuentos recurrentes.
22. Bonos y percepciones manuales.
23. Aprobación formal de pre-nómina.
24. Cierre definitivo de nómina y bloqueo de recálculo.

## Flujo final de nómina

El flujo final validado es:

Empleado activo + contrato + asistencia
→ cálculo de pre-nómina
→ generación de conceptos
→ aplicación de percepciones manuales
→ aplicación de descuentos/préstamos/anticipos
→ solicitud formal de aprobación
→ aprobación o rechazo
→ cierre definitivo
→ bloqueo de recálculo.

## Reglas finales

- Una pre-nómina en borrador puede calcularse.
- Una pre-nómina calculada puede enviarse a aprobación.
- Una pre-nómina pendiente de aprobación no debe recalcularse.
- Una pre-nómina aprobada puede cerrarse.
- Una pre-nómina cerrada queda bloqueada.
- Una pre-nómina cerrada no puede recalcularse.
- El cierre guarda usuario, fecha y motivo.
- PDF y Excel permanecen disponibles para consulta.

## Conceptos de nómina base

Conceptos esperados por empresa:

- SUELDO_BASE
- HORAS_EXTRA
- INCIDENCIAS_PERCEPCION
- INCIDENCIAS_DEDUCCION
- POLITICA_RETARDO
- POLITICA_SALIDA_TEMPRANA
- POLITICA_FALTA
- PRESTAMO_EMPLEADO
- ANTICIPO_NOMINA
- DESCUENTO_RECURRENTE
- BONO_PRODUCTIVIDAD
- COMISION
- GRATIFICACION
- APOYO_TRANSPORTE
- APOYO_COMIDA

## Resultado de prueba integral V5.64.29a

Prueba integral esperada:

- Comisión aplicada: 300.00
- Préstamo aplicado: 150.00
- Bruto esperado: 3800.00
- Deducciones esperadas: 150.00
- Neto esperado: 3650.00
- Estado después de solicitar aprobación: pending_approval
- Estado después de aprobar: approved
- Estado después de cerrar: closed
- Bloqueo de recálculo: sí
- Limpieza de datos temporales: sí

## Pendientes recomendados posteriores

Estos puntos quedan como mejora posterior, no como bloqueo del cierre del módulo:

1. Reapertura controlada de nómina cerrada con autorización especial.
2. Timbrado CFDI de nómina.
3. Integración contable automática de nómina cerrada.
4. Dispersión bancaria o layout bancario.
5. Dashboard ejecutivo de nómina.
6. Comparativo de nómina por periodo.
7. Reportes fiscales/IMSS/ISR más avanzados.
8. Auditoría visual de cambios sensibles en contrato, salario y nómina.
9. Flujo masivo de recibos firmados por empleados.

## Estado

Módulo RRHH/Nómina listo como versión funcional interna para pruebas completas de usuario en DEV.
