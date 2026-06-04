#!/usr/bin/env bash

# Script de validation locale pour le déploiement Railway.com
# Ce script permet de s'assurer que le Dockerfile se build correctement avant le push.

set -e

echo "🚀 Démarrage de la validation du build Docker pour Railway..."

# 1. Vérification de Docker
if ! command -v docker &> /dev/null
then
    echo "⚠️ Docker n'est pas installé ou n'est pas actif localement."
    echo "   Pour tester le build, installez Docker Desktop."
else
    echo "🐳 Docker détecté, lancement du test de build..."
    docker build -t alertbook-railway:latest .
    echo "✅ Image Docker construite avec succès !"
fi

echo ""
echo "=========================================================="
echo "📝 Rappels importants pour le déploiement sur Railway :"
echo "=========================================================="
echo "1. Les migrations s'exécutent automatiquement au démarrage du conteneur."
echo "2. Le stockage des fichiers utilise le disque 'public'."
echo "   -> N'oubliez pas de créer un Volume sur Railway et de le monter sur :"
echo "      /app/storage/app/public"
echo "3. Les emails sont configurés par défaut via Resend (MAIL_MAILER=resend)."
echo "   -> Veillez à ajouter les variables suivantes sur Railway :"
echo "      - MAIL_MAILER=resend"
echo "      - RESEND_API_KEY=re_your_api_key"
echo "      - MAIL_FROM_ADDRESS=notifications@votre-domaine.com"
echo "      - MAIL_FROM_NAME=\"AlertBook\""
echo "4. Pour le serveur web, nous utilisons Nginx + PHP-FPM préconfiguré."
echo "=========================================================="
