FROM node:22-alpine

WORKDIR /app

RUN npm install -g npm@11.14.1

COPY docker-entrypoint.sh /usr/local/bin/gestionev-frontend-entrypoint
RUN chmod +x /usr/local/bin/gestionev-frontend-entrypoint

EXPOSE 5173

ENTRYPOINT ["gestionev-frontend-entrypoint"]
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5173"]
