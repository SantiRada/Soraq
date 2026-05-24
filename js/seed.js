/* Demo seed — injects sample studies + responses into localStorage */
(function seedDemo() {
  const KEY = 'soraq_data';
  const existing = JSON.parse(localStorage.getItem(KEY) || '{"studies":[],"responses":[]}');
  if (existing.studies.length > 0) return; // already seeded

  const STUDY_1 = 'study_demo_nav';
  const STUDY_2 = 'study_demo_settings';

  const items1 = [
    'Panel principal','Mis proyectos','Notificaciones','Configuración de cuenta',
    'Historial de actividad','Reportes','Búsqueda avanzada','Integraciones',
    'Facturación','Usuarios del equipo','Permisos','Plantillas',
    'Exportar datos','Ayuda y soporte','Accesibilidad',
  ];

  const items2 = [
    'Cambiar contraseña','Foto de perfil','Idioma','Zona horaria',
    'Notificaciones por email','Notificaciones push','Plan y facturación',
    'Métodos de pago','Historial de facturas','Eliminar cuenta',
    'Privacidad de datos','API keys',
  ];

  const cats1 = [
    { name: 'Navegación principal' },
    { name: 'Configuración' },
    { name: 'Herramientas' },
    { name: 'Administración' },
  ];

  const studies = [
    {
      id: STUDY_1, title: 'Navegación del dashboard principal',
      type: 'card-sorting-closed', status: 'active',
      createdAt: new Date(Date.now() - 8 * 24 * 3600000).toISOString(),
      responseCount: 0, objective: 'Validar si la arquitectura de información del dashboard refleja los modelos mentales de los usuarios.',
      userRequirements: 'Usuarios activos del producto, uso semanal, perfil tech-savvy.',
      validationQuestions: [{
        text: '¿Con qué frecuencia usás el producto?',
        options: [
          { text: 'Diariamente o casi todos los días', action: 'continue' },
          { text: 'Varias veces a la semana', action: 'continue' },
          { text: 'Ocasionalmente o nunca', action: 'reject', rejectMsg: 'Gracias por tu tiempo. Este estudio está orientado a usuarios frecuentes del producto. ¡Apreciamos tu interés!' },
        ]
      }],
      items: items1, categories: cats1,
      settings: { randomize: true, allowRename: false, maxGroups: null,
        welcomeMsg: 'Este ejercicio nos ayuda a entender cómo organizarías la información del dashboard. No hay respuestas correctas.',
        instructions: 'Organizá las tarjetas en los grupos disponibles. Podés dejar tarjetas sin colocar si no estás seguro.',
        thanksMsg: '¡Muchas gracias! Tus respuestas son muy valiosas para mejorar la experiencia del producto.',
        btnNext: 'Continuar', btnFinish: 'Finalizar ejercicio' },
    },
    {
      id: STUDY_2, title: 'Configuración de cuenta y privacidad',
      type: 'card-sorting-open', status: 'active',
      createdAt: new Date(Date.now() - 2 * 24 * 3600000).toISOString(),
      responseCount: 0, objective: 'Descubrir cómo los usuarios mentalmente organizan las opciones de configuración.',
      userRequirements: 'Cualquier usuario registrado.',
      validationQuestions: [],
      items: items2, categories: [],
      settings: { randomize: true, allowRename: true, maxGroups: null,
        welcomeMsg: 'Organizá estas tarjetas en grupos que tengan sentido para vos. Creá los grupos que necesites y poneles el nombre que prefieras.',
        instructions: 'Creá grupos arrastrando las tarjetas. Nombrá cada grupo como consideres apropiado.',
        thanksMsg: '¡Gracias por participar!',
        btnNext: 'Siguiente', btnFinish: 'Enviar respuestas' },
    },
  ];

  // Generate synthetic responses for study 1
  const GROUPS_PRESETS = [
    [
      { name: 'Navegación principal', cards: ['Panel principal','Mis proyectos','Historial de actividad'] },
      { name: 'Configuración',       cards: ['Configuración de cuenta','Notificaciones','Accesibilidad','Idioma'] },
      { name: 'Herramientas',        cards: ['Búsqueda avanzada','Plantillas','Exportar datos','Reportes'] },
      { name: 'Administración',      cards: ['Usuarios del equipo','Permisos','Integraciones','Facturación'] },
    ],
    [
      { name: 'Navegación principal', cards: ['Panel principal','Mis proyectos','Reportes'] },
      { name: 'Configuración',       cards: ['Configuración de cuenta','Notificaciones','Accesibilidad'] },
      { name: 'Herramientas',        cards: ['Búsqueda avanzada','Plantillas','Exportar datos','Integraciones'] },
      { name: 'Administración',      cards: ['Usuarios del equipo','Permisos','Facturación','Historial de actividad'] },
    ],
    [
      { name: 'Navegación principal', cards: ['Panel principal','Mis proyectos','Notificaciones'] },
      { name: 'Configuración',       cards: ['Configuración de cuenta','Permisos','Accesibilidad'] },
      { name: 'Herramientas',        cards: ['Búsqueda avanzada','Reportes','Exportar datos'] },
      { name: 'Administración',      cards: ['Usuarios del equipo','Facturación','Integraciones','Plantillas'] },
    ],
    [
      { name: 'Inicio',              cards: ['Panel principal','Historial de actividad','Mis proyectos'] },
      { name: 'Mi cuenta',           cards: ['Configuración de cuenta','Notificaciones','Accesibilidad'] },
      { name: 'Trabajo',             cards: ['Plantillas','Exportar datos','Búsqueda avanzada','Reportes'] },
      { name: 'Equipo',              cards: ['Usuarios del equipo','Permisos','Integraciones','Facturación'] },
    ],
    [
      { name: 'Navegación principal', cards: ['Panel principal','Mis proyectos','Reportes','Historial de actividad'] },
      { name: 'Configuración',       cards: ['Configuración de cuenta','Accesibilidad','Notificaciones'] },
      { name: 'Herramientas',        cards: ['Búsqueda avanzada','Exportar datos','Plantillas'] },
      { name: 'Administración',      cards: ['Usuarios del equipo','Permisos','Facturación','Integraciones'] },
    ],
    [
      { name: 'Principal',           cards: ['Panel principal','Mis proyectos'] },
      { name: 'Configuración',       cards: ['Configuración de cuenta','Notificaciones','Permisos','Accesibilidad'] },
      { name: 'Utilidades',          cards: ['Búsqueda avanzada','Reportes','Plantillas','Exportar datos'] },
      { name: 'Admin',               cards: ['Usuarios del equipo','Facturación','Integraciones','Historial de actividad'] },
    ],
    [
      { name: 'Navegación principal', cards: ['Panel principal','Mis proyectos','Notificaciones','Reportes'] },
      { name: 'Cuenta',              cards: ['Configuración de cuenta','Accesibilidad'] },
      { name: 'Herramientas',        cards: ['Búsqueda avanzada','Exportar datos','Integraciones'] },
      { name: 'Equipo y admin',      cards: ['Usuarios del equipo','Permisos','Facturación','Plantillas','Historial de actividad'] },
    ],
    [
      { name: 'Navegación principal', cards: ['Panel principal','Mis proyectos','Historial de actividad','Reportes'] },
      { name: 'Configuración',       cards: ['Configuración de cuenta','Notificaciones','Accesibilidad','Permisos'] },
      { name: 'Herramientas',        cards: ['Búsqueda avanzada','Plantillas','Exportar datos'] },
      { name: 'Administración',      cards: ['Usuarios del equipo','Facturación','Integraciones'] },
    ],
  ];

  const responses = GROUPS_PRESETS.map((groups, i) => ({
    id:            `resp_demo_${i}`,
    studyId:       STUDY_1,
    participantId: `p_demo_${i}`,
    completedAt:   new Date(Date.now() - (7 - i) * 24 * 3600000 - i * 3600000).toISOString(),
    answers:       { '¿Con qué frecuencia usás el producto?': ['Diariamente','Varias veces a la semana','Semanalmente','Diariamente','Varias veces a la semana','Ocasionalmente','Diariamente','Diariamente'][i] },
    groups,
    unplaced: [],
  }));

  studies[0].responseCount = responses.length;

  // Responses for study 2 (open card sort)
  const openResponses = [
    {
      id: 'resp_open_0', studyId: STUDY_2, participantId: 'p_o_0',
      completedAt: new Date(Date.now() - 1 * 3600000).toISOString(), answers: {},
      groups: [
        { name: 'Seguridad',    cards: ['Cambiar contraseña','Eliminar cuenta','API keys','Privacidad de datos'] },
        { name: 'Mi perfil',    cards: ['Foto de perfil','Idioma','Zona horaria'] },
        { name: 'Facturación',  cards: ['Plan y facturación','Métodos de pago','Historial de facturas'] },
        { name: 'Alertas',      cards: ['Notificaciones por email','Notificaciones push'] },
      ], unplaced: [],
    },
    {
      id: 'resp_open_1', studyId: STUDY_2, participantId: 'p_o_1',
      completedAt: new Date(Date.now() - 30 * 60000).toISOString(), answers: {},
      groups: [
        { name: 'Cuenta',       cards: ['Foto de perfil','Cambiar contraseña','Eliminar cuenta'] },
        { name: 'Preferencias', cards: ['Idioma','Zona horaria','Notificaciones por email','Notificaciones push'] },
        { name: 'Pagos',        cards: ['Plan y facturación','Métodos de pago','Historial de facturas'] },
        { name: 'Seguridad',    cards: ['API keys','Privacidad de datos'] },
      ], unplaced: [],
    },
  ];
  studies[1].responseCount = openResponses.length;

  localStorage.setItem(KEY, JSON.stringify({
    studies,
    responses: [...responses, ...openResponses],
  }));
})();
