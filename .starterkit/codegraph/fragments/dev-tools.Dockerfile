# CodeGraph (local code-intelligence graph + MCP server for the agents). The npm package ships
# the native binary as an optionalDependency, so the build needs no runtime download. Telemetry
# defaults to ON upstream and the home dir isn't a persisted volume, so it's disabled via env.
ENV CODEGRAPH_TELEMETRY=0
RUN npm install -g @colbymchenry/codegraph@1.6.0 && npm cache clean --force
